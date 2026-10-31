<?php

namespace Tests\Unit;

use App\Domain\Model\AccessToken;
use App\Domain\Model\CreditCard;
use App\Domain\Model\EWallet;
use App\Domain\Model\Holder;
use App\Domain\Model\MobileAccount;
use App\Domain\Model\Payment;
use App\Domain\Model\PaymentServiceProvider;
use App\Domain\Model\Tenant;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payment_constants_and_creation(): void
    {
        $this->assertSame('PENDING', Payment::PENDING);
        $this->assertSame('SUCCESS', Payment::SUCCESS);
        $this->assertSame('ACCEPTED', Payment::ACCEPTED);
        $this->assertSame('FAILED', Payment::FAILED);

        $tenantId = (string) Str::uuid();
        Tenant::create([
            'id' => $tenantId,
            'name' => 'Model Test Tenant',
            'city' => 'Douala',
            'location' => 'Akwa',
            'region' => 'Littoral',
            'country' => 'Cameroon',
            'phone_number' => '+237699000001',
            'active' => true,
            'owner' => (string) Str::uuid(),
            'network' => json_encode(['id' => 'ASSO-NET']),
        ]);

        $holderId = (string) Str::uuid();
        Holder::create([
            'id' => $holderId,
            'tenant_id' => $tenantId,
            'type' => 'MERCHANT_ADMINISTRATOR',
            'full_name' => 'John Doe',
            'username' => 'johndoe_' . Str::random(5),
            'email' => 'john.doe@example.com',
            'active' => true,
        ]);

        $pspId = (string) Str::uuid();
        PaymentServiceProvider::create([
            'id' => $pspId,
            'name' => 'MTN CAMEROON',
            'payment_method' => PaymentServiceProvider::MOBILEMONEY,
            'api_url' => 'https://sandbox.momodeveloper.mtn.com',
        ]);

        $currencyId = (string) Str::uuid();
        Currency::create([
            'id' => $currencyId,
            'name' => 'Euro',
            'symbol' => 'EUR',
        ]);

        $paymentId = (string) Str::uuid();
        $orderId = (string) Str::uuid();

        $payment = Payment::create([
            'id' => $paymentId,
            'tenant_id' => $tenantId,
            'holder_id' => $holderId,
            'payment_service_provider_id' => $pspId,
            'account_used' => json_encode(['accountnumber' => '670000001']),
            'amount' => 1500.50,
            'currency_id' => $currencyId,
            'order_number' => $orderId,
            'start_date' => Carbon::now(),
            'end_date' => null,
            'status' => Payment::PENDING,
            'details' => 'Payment initiated',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => Payment::PENDING,
            'amount' => 1500.50,
        ]);

        $payment->status = Payment::SUCCESS;
        $payment->end_date = Carbon::now();
        $payment->save();

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => Payment::SUCCESS,
        ]);
    }

    public function test_payment_service_provider_constants_and_attributes(): void
    {
        $this->assertSame('MOBILEMONEY', PaymentServiceProvider::MOBILEMONEY);
        $this->assertSame('LOFOMBO', PaymentServiceProvider::LOFOMBO);
        $this->assertSame('CREDIT_CARD', PaymentServiceProvider::CREDIT_CARD);

        $id = (string) Str::uuid();
        $psp = PaymentServiceProvider::create([
            'id' => $id,
            'name' => 'ORANGE MONEY',
            'payment_method' => PaymentServiceProvider::MOBILEMONEY,
            'api_url' => 'https://api.orange.cm',
            'icon' => 'orange.png',
        ]);

        $this->assertDatabaseHas('payment_service_providers', [
            'id' => $id,
            'name' => 'ORANGE MONEY',
            'payment_method' => 'MOBILEMONEY',
        ]);
    }

    public function test_tenant_and_holder_creation(): void
    {
        $tenantId = (string) Str::uuid();
        $ownerId = (string) Str::uuid();

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'ACME Trading Co',
            'city' => 'Yaounde',
            'location' => 'Bastos',
            'region' => 'Centre',
            'country' => 'Cameroon',
            'phone_number' => '+237677000002',
            'website' => 'https://acme.cm',
            'address' => 'BP 1234',
            'active' => true,
            'taxpayer_number' => 'M0123456789A',
            'number_trade_register' => 'RC/YAO/2026/B/001',
            'owner' => $ownerId,
            'network' => json_encode(['network' => 'ASSO-NETWORK']),
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'ACME Trading Co',
            'active' => true,
        ]);

        $holderId = (string) Str::uuid();
        $username = 'janedoe_' . Str::random(5);
        $holder = Holder::create([
            'id' => $holderId,
            'tenant_id' => $tenantId,
            'type' => 'MERCHANT_ADMINISTRATOR',
            'account_id' => (string) Str::uuid(),
            'full_name' => 'Jane Doe',
            'username' => $username,
            'email' => 'jane@acme.cm',
            'phone_numbers' => json_encode(['+237677000002']),
            'active' => true,
        ]);

        $this->assertDatabaseHas('holders', [
            'id' => $holderId,
            'tenant_id' => $tenantId,
            'username' => $username,
        ]);
    }

    public function test_access_token_creation_and_revocation(): void
    {
        $tokenId = (string) Str::uuid();
        $token = AccessToken::create([
            'id' => $tokenId,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Test Token',
            'scopes' => json_encode(['payment:read', 'payment:write']),
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->assertDatabaseHas('access_tokens', [
            'id' => $tokenId,
            'revoked' => false,
        ]);

        $token->revoked = true;
        $token->save();

        $this->assertDatabaseHas('access_tokens', [
            'id' => $tokenId,
            'revoked' => true,
        ]);
    }

    public function test_mobile_account_and_credit_card_and_ewallet(): void
    {
        $tenantId = (string) Str::uuid();
        $holderId = (string) Str::uuid();

        Tenant::create([
            'id' => $tenantId,
            'name' => 'Accounts Tenant',
            'city' => 'Douala',
            'active' => true,
            'owner' => (string) Str::uuid(),
            'network' => json_encode(['id' => 'ACC-NET']),
        ]);

        Holder::create([
            'id' => $holderId,
            'tenant_id' => $tenantId,
            'type' => 'MERCHANT_USER',
            'full_name' => 'Alice Accounts',
            'username' => 'alice_' . Str::random(5),
            'email' => 'alice@example.com',
            'active' => true,
        ]);

        $mobileId = (string) Str::uuid();
        MobileAccount::create([
            'id' => $mobileId,
            'tenant_id' => $tenantId,
            'holder_id' => $holderId,
            'phone_number' => '+237699112233',
            'holder_name' => 'Alice Accounts',
            'issuer' => 'MTN',
            'active' => true,
        ]);

        $this->assertDatabaseHas('mobile_accounts', [
            'id' => $mobileId,
            'phone_number' => '+237699112233',
        ]);

        $cardId = (string) Str::uuid();
        CreditCard::create([
            'id' => $cardId,
            'tenant_id' => $tenantId,
            'holder_id' => $holderId,
            'card_number' => '4111111111111111',
            'holder_name' => 'Alice Accounts',
            'expiration_date' => '2028-12-31',
            'issuer_name' => 'Visa',
            'active' => true,
        ]);

        $this->assertDatabaseHas('credit_cards', [
            'id' => $cardId,
            'card_number' => '4111111111111111',
        ]);

        $walletId = (string) Str::uuid();
        EWallet::create([
            'id' => $walletId,
            'holder_id' => $holderId,
            'accounts' => json_encode(['wallet_id' => 'W-999']),
            'active' => true,
        ]);

        $this->assertDatabaseHas('e_wallets', [
            'id' => $walletId,
        ]);
    }

    public function test_currency_and_country_models(): void
    {
        $currencyId = (string) Str::uuid();
        Currency::create([
            'id' => $currencyId,
            'name' => 'Central African CFA Franc',
            'symbol' => 'XAF',
            'is_fund' => false,
            'is_complimentary' => false,
            'is_metal' => false,
            'sk_currency' => 'XAF',
        ]);

        $this->assertDatabaseHas('currencies', [
            'id' => $currencyId,
            'symbol' => 'XAF',
        ]);

        $countryCode = strtoupper(Str::random(2));
        Country::create([
            'code' => $countryCode,
            'name' => 'Test Land',
        ]);

        $this->assertDatabaseHas('countries', [
            'code' => $countryCode,
            'name' => 'Test Land',
        ]);
    }
}
