# Smart Trade Platform — Payment Module

The **Payment Module** is a PHP/Laravel application intended to support payment processing within **Smart Trade Platform (STP)**. It contains payment records, tenant and account models, an initial MTN Cameroon mobile-money integration, and RabbitMQ messaging components for communication with other STP services.

**Project lead:** NKALLA EHAWE Didier Junior

**Status:** prototype under development. Payment processing, service integration and security controls require further verification and remediation before production use.

## Why this project exists

STP aims to help wholesalers and vendors manage goods supplied on credit, repayments and returns. The broader vision includes building a trading history that participants may use when engaging with financial institutions. Lending decisions remain with those institutions.

This repository focuses on the payment component of that vision. It is intended to work alongside the [STP identity and access management service](https://github.com/angedid/asso-iam) and the [Angular web client](https://github.com/angedid/angular-client). It does not implement the entire STP product.

## Current scope and status

| Area | Code currently present | Status of this review |
| --- | --- | --- |
| Payment requests | A payment endpoint validates selected fields, creates a payment record and contains MTN Cameroon mobile-money request logic. | Present in source; not verified end to end. |
| Payment status | The model defines `PENDING`, `ACCEPTED`, `SUCCESS` and `FAILED`. | Status transitions and final settlement require further review. |
| Tenant and holder data | Models, migrations and a tenant-provisioning endpoint are present. | Integration with IAM and tenant isolation are not verified. |
| IAM events | Endpoints and RabbitMQ listener scripts handle tenant provisioning, access-token information and logout-related messages. | Message delivery and authorization have not been verified end to end. |
| Payment events | Jobs publish payment-initiated and payment-completed messages; the completed event includes a signature. | Signing, delivery and verification require further review. |
| Other payment methods | Models or branches mention mobile accounts, credit cards, e-wallets, Orange Cameroon and LOFOMBO. | These declarations do not establish that the methods are implemented or operational. |

No real payment, reconciliation, bank transfer or mobile-money settlement was performed during this review. The code currently includes specific security and correctness issues described below; do not use it for real transactions in its present state.

## Technology stack

| Component | Repository configuration |
| --- | --- |
| Runtime | PHP `^8.2`, subject to installed dependency and extension requirements |
| Framework | Laravel `^12.0` |
| Authentication dependencies | Laravel Passport `^13.4` and Sanctum `^4.0`; route behavior differs by middleware |
| Data access | Eloquent models and Laravel migrations |
| Messaging | `php-amqplib/php-amqplib` and Laravel queued jobs |
| Cryptography | `spatie/crypto` for event signing |
| Frontend assets | Vite and Tailwind CSS, as declared in `package.json` |
| Tests | PHPUnit `^11.5.3` and Laravel's test runner |

`composer.lock` is present. The reviewed commit does **not** contain a `package-lock.json`; JavaScript dependencies are therefore not pinned by an npm lockfile in this version.

## Prerequisites

- A compatible PHP version, Composer and the PHP extensions required by Laravel and the locked Composer packages, including a PDO driver for the chosen database.
- A dedicated development database supported by the configured Laravel connection.
- Node.js and npm compatible with the versions specified in `package.json` if building the web assets.
- An isolated RabbitMQ instance and an appropriately configured queue backend when testing messaging.
- A **sandbox** configuration for MTN mobile money only when deliberately testing the provider integration; never use real payment credentials or payment data for basic setup.
- A configured IAM service or test doubles when testing service-to-service integration.

The default `.env.example` does not list every payment or messaging setting used by the application. Inspect the code and supply required development-only values through an appropriate secrets-management mechanism.

## Local setup

The following is a starting procedure for a clean development environment. It was **not executed during the repository review**. Validate migrations and integrations in an isolated environment before relying on it.

```bash
git clone https://github.com/angedid/asso-payment.git
cd asso-payment
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` for the development application and database. Create an empty development database, then run:

```bash
php artisan migrate
```

The repository contains payment, tenant, holder, currency, provider, access-token and Passport-related migrations. Their compatibility with a particular database has not been tested here. The default `DatabaseSeeder` creates a generic Laravel user and does not establish the complete tenant, holder, provider and currency data needed for a payment request.

If the web assets are needed, install the npm dependencies and build them:

```bash
npm install
npm run build
```

Since no npm lockfile is included, the precise dependency versions may vary. Review the generated lockfile and dependency changes if you decide to introduce one.

Start a local development server on a port that does not conflict with IAM or Angular:

```bash
php artisan serve --host=127.0.0.1 --port=9002
```

Set `APP_URL` and the local messaging configuration consistently with your chosen host and port. `9002` is an example, not a repository-enforced port. The registered health route is `/up`.

## IAM and authentication integration

`routes/api.php` declares three integration endpoints using the `rabbitmq.client` middleware alias. In `bootstrap/app.php`, this alias points to Passport's `CheckToken` middleware. Listener scripts obtain service credentials and call the payment service. The API also contains `/api/user` with `auth:sanctum`, while `/api/payments` uses the custom `iam.auth.api` alias mapped to `ControlAccess`.

Do not assume Passport, Sanctum and the custom middleware provide equivalent authorization guarantees. Configure service clients and keys only after reviewing the installed Passport version and the intended architecture. Run `php artisan route:list` in a configured environment to verify the routes and middleware actually registered.

**Important:** the current `ControlAccess` implementation decodes token payload data and looks up a token identifier, but the reviewed code does not visibly verify the token signature and falls through to the next handler when no token or matching record is found. It must not be treated as a sufficient authorization boundary. Review authentication, signature validation, tenant ownership and permissions before any deployment.

## API overview

These declarations are in `routes/api.php`; Laravel applies the `/api` prefix. The table describes source code at the reviewed revision and does not claim endpoint behavior has been tested.

| Method | Path | Purpose in the code | Route middleware |
| --- | --- | --- | --- |
| `GET` | `/api/test` | Return a simple test response | None declared |
| `GET` | `/api/user` | Return the authenticated user | `auth:sanctum` |
| `POST` | `/api/tenant-provisions` | Create local tenant and holder records from an incoming message | `rabbitmq.client` |
| `POST` | `/api/access-tokens` | Record access-token information from an incoming message | `rabbitmq.client` |
| `POST` | `/api/logout` | Mark a local token record as revoked using its identifier | `rabbitmq.client` |
| `POST` | `/api/payments` | Validate payment data and invoke the selected payment path | `iam.auth.api` |

The payment endpoint expects fields including `user_id`, `payment_service_provider_id`, `tenant_id`, `amount`, `currency_id` and `order_id`. The mobile-money branch also validates phone-number input. Refer to `ApiController::makeServicePayment` and the database schema for the current contract; do not send real payment data during development.

Several controller responses use HTTP `200` even for application-level failures, with fields such as `success`, `faillure`, `raison` or `response`. A `200` response alone is therefore not sufficient to infer payment success or final settlement. Verify both the response contract and provider status before building a client integration.

## Payment processing and messaging

The MTN Cameroon branch reads provider configuration from `MOMO_PAYMENT_ACCOUNT_DATA`, requests a provider access token, creates a local payment record, sends a request to pay, and performs a subsequent provider status request. It dispatches events using settings including `LICENSE_PAYMENT_INITIATED_EXCHANGE`, `LICENSE_PAYMENT_COMPLETED_EXCHANGE` and `RABBIT_MQ_EXCHANGE_TYPE`. `MTN_USSD_PAYMENT_CODE` is used in an informational message.

Review the current code before configuring this integration:

- The outgoing MTN request currently hard-codes the currency as `EUR`, even though a `currency_id` is received and looked up. Supported currencies and provider configuration must be validated before testing.
- An accepted request is not necessarily a completed payment. The current status-check path can mark a payment `SUCCESS` on an HTTP `200` response without visibly checking the provider's returned payment status. Settlement, retries, asynchronous updates and idempotency require review and tests.
- The completed-event signing code reads `payment-private.pem` and `payment-public.pem` via relative paths. Resolve the working-directory dependency and store private keys outside the public directory and source control.

The messaging listeners are under `app/Infrastructure/Messaging/`; the publisher is `app/Jobs/ProcessMessages.php`. They read `global-var-config.ini` using relative paths. Required host, broker, exchange, queue and client settings depend on the process working directory and are not fully represented in `.env.example`. Review and configure them without copying real secrets into the repository. Run a queue worker such as `php artisan queue:work` only after configuring its backend and the necessary integrations.

## Tests and checks

The test suite covers the primary business logic, domain models, authentication/middleware layers, and payment use cases.

### Running Automated Tests

Execute the test suite using either of the following documented commands:

```bash
# Recommended: clears Laravel configuration cache and runs all tests
composer test

# Or run directly via Artisan
php artisan test
```

To run a specific test suite or test class:

```bash
# Run unit tests only
php artisan test --testsuite=Unit

# Run feature tests only
php artisan test --testsuite=Feature

# Run a specific test file
php artisan test tests/Feature/PaymentTest.php
```

### Test Suite Coverage

- **Domain Models & Constraints (`tests/Unit/DomainModelTest.php`):** Validates creation, status transitions, JSON casting, and constraints for `Payment`, `PaymentServiceProvider`, `Tenant`, `Holder`, `AccessToken`, `MobileAccount`, `CreditCard`, `EWallet`, `Currency`, and `Country`.
- **Middleware & Token Validation (`tests/Unit/ControlAccessMiddlewareTest.php`, `tests/Feature/AuthAndMiddlewareTest.php`):** Validates access token expiration checks, revocation status, missing/unauthenticated bearer token handling, and Passport `rabbitmq.client` route protection.
- **Tenant Provisioning (`tests/Feature/TenantProvisionTest.php`):** Tests transactional creation of tenant and admin holder records as well as rollback behavior on malformed input.
- **Access Token Management & Revocation (`tests/Feature/AccessTokenManagementTest.php`):** Validates decoding and persistence of token metadata (`/api/access-tokens`) and token revocation upon logout (`/api/logout`).
- **Payment Processing & Use Cases (`tests/Feature/PaymentTest.php`):**
  - Validation rules for required payment fields, UUID formats, and foreign keys.
  - Mobile Money phone number validation against configured countries.
  - Idempotency / duplicate payment prevention for already settled orders (`Order Yey Paid`).
  - MTN Cameroon payment flow with provider response handling (HTTP 202 Accepted, HTTP 200 Success with RSA event signing, HTTP 4xx/5xx failures, and network exception resilience).
  - Credit Card and Lofombo payment method validation handling.
- **Background Jobs & Event Messaging (`tests/Unit/ProcessMessagesTest.php`):** Validates `ProcessMessages` job construction, queue serialization, and event dispatch parameters.
- **Result Formatting (`tests/Unit/GlobalResultHandlerTest.php`):** Tests success and failure response payload builders.

## Continuous Integration and Docker

### Continuous Integration (GitHub Actions)

A GitHub Actions CI workflow is configured under `.github/workflows/ci.yml`. It runs automatically on pushes and pull requests to `main`:

1. **Automated Test Job:** Spins up a PostgreSQL 16 container, provisions required extensions (PHP 8.4 with `pdo_pgsql`, `sockets`, `intl`, etc.), installs locked Composer dependencies, runs database migrations, and executes `composer test`.
2. **Docker Build Job:** Validates that the application `Dockerfile` builds cleanly.

### Docker Support

A production-ready `Dockerfile` (PHP 8.4 CLI) and a multi-container `docker-compose.yml` are included for local development and containerized testing:

```bash
# Build the Docker image
docker build -t asso-payment:latest .

# Run the full stack (Application, PostgreSQL, RabbitMQ)
docker compose up -d

# Run automated tests inside the Docker container
docker compose run --rm app composer test
```

## Repository structure

| Path | Purpose |
| --- | --- |
| `routes/api.php`, `routes/web.php` | API and web route declarations |
| `app/Http/Controllers/ApiController.php` | Tenant provisioning, token records, logout and payment handling |
| `app/Http/Middleware/ControlAccess.php` | Custom payment-route token check |
| `app/Domain/Model/` | Payment, tenant, holder, account, provider and token models |
| `app/Jobs/` | Queued message-publishing job |
| `app/Infrastructure/Messaging/` | RabbitMQ listener scripts and integration configuration |
| `app/Models/` | Laravel user, country and currency models |
| `config/` | Application, database, queue and authentication configuration |
| `database/migrations/`, `database/seeders/` | Database schema and starter seeder |
| `resources/` | Blade view and frontend assets |
| `tests/Unit/`, `tests/Feature/` | Comprehensive test suite (Models, Auth/Middleware, Payment, Tenant, Tokens, Jobs) |
| `.github/workflows/ci.yml` | GitHub Actions CI workflow (PostgreSQL, testing, Docker build) |
| `Dockerfile`, `docker-compose.yml` | Containerized runtime, testing, and multi-service orchestration |

## Security and development limitations

The following are concrete findings from the reviewed source and should be addressed before deploying or handling real transactions:

- **Private key in the public repository:** `public/payment-private.pem` is tracked inside the web-served directory. Treat it as exposed. Remove private keys from public paths and repository history through an appropriate remediation process; rotate any key that was actually used. The tracked `token.json` and `global-var-config.ini` files also require confidential review for sensitive values, without copying them into tickets or documentation.
- **Authentication weaknesses:** `ControlAccess` logs bearer tokens, does not visibly verify token signatures, and allows some missing or unknown tokens to reach the next handler. Fix and test authentication and authorization, including tenant isolation.
- **Sensitive logging:** the payment controller logs provider tokens, authorization headers and request bodies. Remove sensitive values from logs and review previously generated logs.
- **Payment status and currency:** validate currency selection, provider response content, final settlement, retries and duplicate-request handling. Do not equate an HTTP success response with a settled transaction.
- **Test coverage:** add focused automated tests for rejected and forged tokens, cross-tenant access, payment validation, provider failures and status transitions, signing and message handling.

These points describe code and files observed in the repository; they are not a completed security audit.

## Contributing

Describe the user need, scope and verification procedure for each change. Use synthetic fixtures, sandbox providers and isolated messaging services. Add meaningful tests for payment status, security and failure handling. Report which checks were run and which integrations could not be exercised.

Do not commit real user information, payment-card data, passwords, access tokens, private keys or provider credentials. Third-party libraries and assets retain their own license terms.

## License

`composer.json` declares `MIT`, but no root `LICENSE` file was present in the reviewed repository. Confirm the rights in the project code and add the intended license text before presenting the repository as licensed. Third-party dependencies and assets remain subject to their own licenses.

## Product background

The STP vision is based on “Smart Trade Platform — Executive Summary,” version 1.0, August 2026, by Didier Junior NKALLA EHAWE. The business capabilities described there are product goals; the module's current code and verification status are stated above.
