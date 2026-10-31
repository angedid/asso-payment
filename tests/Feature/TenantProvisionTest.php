<?php

namespace Tests\Feature;

use App\Domain\Model\Holder;
use App\Domain\Model\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CheckToken;
use Tests\TestCase;

class TenantProvisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_provision_tenant_successfully(): void
    {
        $tenantId = (string) Str::uuid();
        $adminUserId = (string) Str::uuid();
        $username = 'admin_' . Str::random(6);

        $payload = [
            'data' => [
                'tenant' => [
                    'id' => $tenantId,
                    'name' => 'Acme Corporation Ltd',
                    'city' => 'Douala',
                    'location' => 'Bonanjo',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'phone_number' => '+237699000111',
                    'website' => 'https://acme-corp.cm',
                    'address' => '123 Commercial Avenue',
                    'active' => true,
                    'taxpayer_number' => 'M0987654321B',
                    'number_trade_register' => 'RC/DLA/2026/A/1234',
                    'network' => json_encode(['cluster' => 'CM-WEST']),
                ],
                'user' => [
                    'id' => $adminUserId,
                    'tenant_id' => $tenantId,
                    'full_name' => 'Chief Admin',
                    'username' => $username,
                    'email' => 'admin@acme-corp.cm',
                    'phone_number' => '+237699000111',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'active' => true,
                    'network' => json_encode(['cluster' => 'CM-WEST']),
                    'type' => 'MERCHANT_ADMINISTRATOR',
                ],
            ],
        ];

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/tenant-provisions',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($payload)
            );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Tenant Provision saved successfully',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'Acme Corporation Ltd',
            'owner' => $adminUserId,
        ]);

        $this->assertDatabaseHas('holders', [
            'id' => $adminUserId,
            'tenant_id' => $tenantId,
            'username' => $username,
            'full_name' => 'Chief Admin',
            'type' => 'MERCHANT_ADMINISTRATOR',
        ]);
    }

    public function test_provision_tenant_rolls_back_on_error(): void
    {
        $malformedPayload = [
            'data' => [
                'tenant' => [
                    // Missing required id and other non-nullable fields
                    'name' => 'Broken Tenant',
                ],
                'user' => [
                    'full_name' => 'Admin',
                ],
            ],
        ];

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/tenant-provisions',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($malformedPayload)
            );

        $response->assertStatus(200);
        $content = $response->json();

        $this->assertSame(0, $content['success']);
        $this->assertSame(1, $content['faillure']);
        $this->assertStringContainsString('Faild to save Tenant Provision', $content['raison']);
    }
}
