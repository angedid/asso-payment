<?php

namespace Tests\Feature;

use App\Domain\Model\Holder;
use App\Domain\Model\MobileAccount;
use App\Domain\Model\Payment;
use App\Domain\Model\PaymentServiceProvider;
use App\Domain\Model\Tenant;
use App\Jobs\ProcessMessages;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use DatabaseTransactions;

    private string $tenantId;
    private string $holderId;
    private string $mtnPspId;
    private string $currencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        Tenant::create([
            'id' => $this->tenantId,
            'name' => 'Payment Test Tenant',
            'city' => 'Douala',
            'location' => 'Akwa',
            'region' => 'Littoral',
            'country' => 'Cameroon',
            'phone_number' => '+237699000001',
            'active' => true,
            'owner' => (string) Str::uuid(),
            'network' => json_encode(['cluster' => 'CM-TEST']),
        ]);

        $this->holderId = (string) Str::uuid();
        Holder::create([
            'id' => $this->holderId,
            'tenant_id' => $this->tenantId,
            'type' => 'MERCHANT_USER',
            'full_name' => 'Payment User',
            'username' => 'payuser_' . Str::random(6),
            'email' => 'payuser@example.com',
            'active' => true,
        ]);

        $this->mtnPspId = (string) Str::uuid();
        PaymentServiceProvider::create([
            'id' => $this->mtnPspId,
            'name' => 'MTN CAMEROON',
            'payment_method' => PaymentServiceProvider::MOBILEMONEY,
            'api_url' => 'https://sandbox.momodeveloper.mtn.com',
        ]);

        $this->currencyId = (string) Str::uuid();
        Currency::create([
            'id' => $this->currencyId,
            'name' => 'Euro',
            'symbol' => 'EUR',
        ]);

        // Ensure Cameroon country code exists for phone validation
        if (!Country::where('code', 'CM')->exists()) {
            Country::create([
                'code' => 'CM',
                'name' => 'Cameroon',
            ]);
        }
    }

    public function test_payment_fails_validation_when_required_fields_missing(): void
    {
        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertNotEmpty($data['raison']);
    }

    public function test_payment_fails_validation_when_foreign_keys_do_not_exist(): void
    {
        $payload = [
            'user_id' => (string) Str::uuid(),
            'payment_service_provider_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'amount' => 5000,
            'currency_id' => (string) Str::uuid(),
            'order_id' => (string) Str::uuid(),
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertNotEmpty($data['raison']);
    }

    public function test_payment_fails_when_order_is_not_uuid(): void
    {
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 5000,
            'currency_id' => $this->currencyId,
            'order_id' => 'not-a-valid-uuid',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertStringContainsString('order id', strtolower($data['raison']));
    }

    public function test_payment_fails_validation_with_invalid_phone_number(): void
    {
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 5000,
            'currency_id' => $this->currencyId,
            'order_id' => (string) Str::uuid(),
            'phone_number' => '12345',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertNotEmpty($data['raison']);
    }

    public function test_payment_fails_when_order_already_paid(): void
    {
        $orderId = (string) Str::uuid();

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantId,
            'holder_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'account_used' => json_encode(['accountnumber' => '670000000']),
            'amount' => 1000,
            'currency_id' => $this->currencyId,
            'order_number' => $orderId,
            'start_date' => Carbon::now(),
            'status' => Payment::SUCCESS,
            'details' => 'Already paid order',
        ]);

        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 1000,
            'currency_id' => $this->currencyId,
            'order_id' => $orderId,
            'phone_number' => '+237677000001',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertSame('Order Yey Paid', $data['raison']);
    }

    public function test_mtn_cameroon_payment_accepted_flow(): void
    {
        Queue::fake();

        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/collection/token/' => Http::response([
                'access_token' => 'mock-mtn-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay' => Http::response([], 202),
            'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/*' => Http::response([], 202),
        ]);

        $orderId = (string) Str::uuid();
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 2500,
            'currency_id' => $this->currencyId,
            'order_id' => $orderId,
            'phone_number' => '+237677000002',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(1, $data['success']);
        $this->assertSame(0, $data['faillure']);
        $this->assertStringContainsString('accepted', strtolower($data['response']));

        $this->assertDatabaseHas('payments', [
            'order_number' => $orderId,
            'status' => Payment::ACCEPTED,
            'amount' => 2500,
        ]);

        $this->assertDatabaseHas('mobile_accounts', [
            'tenant_id' => $this->tenantId,
            'holder_id' => $this->holderId,
            'phone_number' => '+237677000002',
        ]);

        Queue::assertPushed(ProcessMessages::class, function ($job) {
            $reflector = new \ReflectionClass($job);
            $exchangeProp = $reflector->getProperty('exchangeName');
            $exchangeProp->setAccessible(true);
            return $exchangeProp->getValue($job) === env('LICENSE_PAYMENT_INITIATED_EXCHANGE');
        });
    }

    public function test_mtn_cameroon_payment_success_flow_with_rsa_signature(): void
    {
        Queue::fake();

        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/collection/token/' => Http::response([
                'access_token' => 'mock-mtn-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay' => Http::response([], 200),
            'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/*' => Http::response([], 200),
        ]);

        $orderId = (string) Str::uuid();
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 5000,
            'currency_id' => $this->currencyId,
            'order_id' => $orderId,
            'phone_number' => '+237677000003',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(1, $data['success']);
        $this->assertSame(0, $data['faillure']);
        $this->assertStringContainsString('successfully', strtolower($data['response']));

        $this->assertDatabaseHas('payments', [
            'order_number' => $orderId,
            'status' => Payment::SUCCESS,
        ]);

        // Verify both initiated and completed messages were dispatched
        Queue::assertPushed(ProcessMessages::class, 2);
    }

    public function test_mtn_cameroon_payment_failed_flow(): void
    {
        Queue::fake();

        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/collection/token/' => Http::response([
                'access_token' => 'mock-mtn-access-token',
            ], 200),
            'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay' => Http::response([
                'message' => 'Internal server error from provider',
            ], 500),
        ]);

        $orderId = (string) Str::uuid();
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 3000,
            'currency_id' => $this->currencyId,
            'order_id' => $orderId,
            'phone_number' => '+237677000004',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertStringContainsString('failed', strtolower($data['response']));

        $this->assertDatabaseHas('payments', [
            'order_number' => $orderId,
            'status' => Payment::FAILED,
        ]);
    }

    public function test_mtn_cameroon_payment_handles_exception(): void
    {
        Queue::fake();

        Http::fake([
            'https://sandbox.momodeveloper.mtn.com/collection/token/' => function () {
                throw new \Exception('Network connection timeout to MTN sandbox');
            },
        ]);

        $orderId = (string) Str::uuid();
        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $this->mtnPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 1200,
            'currency_id' => $this->currencyId,
            'order_id' => $orderId,
            'phone_number' => '+237677000005',
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertStringContainsString('Network connection timeout', $data['raison']);
    }

    public function test_payment_unknown_provider_method(): void
    {
        $unknownPspId = (string) Str::uuid();
        PaymentServiceProvider::create([
            'id' => $unknownPspId,
            'name' => 'CRYPTO GATEWAY',
            'payment_method' => 'BITCOIN',
            'api_url' => 'https://api.crypto.example.com',
        ]);

        $payload = [
            'user_id' => $this->holderId,
            'payment_service_provider_id' => $unknownPspId,
            'tenant_id' => $this->tenantId,
            'amount' => 100,
            'currency_id' => $this->currencyId,
            'order_id' => (string) Str::uuid(),
        ];

        $response = $this->call(
            'POST',
            '/api/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertSame('Unknown Payment Method Provided', $data['raison']);
    }
}
