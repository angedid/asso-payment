<?php

namespace Tests\Feature;

use App\Domain\Model\AccessToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthAndMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_api_test_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/test');

        $response->assertStatus(200);
        $response->assertJson(['test' => true]);
    }

    public function test_tenant_provisions_requires_rabbitmq_client_authentication(): void
    {
        $response = $this->postJson('/api/tenant-provisions', []);

        // Passport's CheckToken middleware throws AuthenticationException -> 401 Unauthorized
        $response->assertStatus(401);
    }

    public function test_access_tokens_requires_rabbitmq_client_authentication(): void
    {
        $response = $this->postJson('/api/access-tokens', []);

        $response->assertStatus(401);
    }

    public function test_logout_requires_rabbitmq_client_authentication(): void
    {
        $response = $this->postJson('/api/logout', []);

        $response->assertStatus(401);
    }

    public function test_payment_endpoint_blocks_expired_bearer_token(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Expired Token',
            'scopes' => '[]',
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->subMinutes(30),
        ]);

        $jwtPayload = base64_encode(json_encode([
            'sub' => 'user-test',
            'jti' => $jti,
        ]));
        $fakeBearer = "header.{$jwtPayload}.sig";

        $response = $this->withHeader('Authorization', 'Bearer ' . $fakeBearer)
            ->postJson('/api/payments', []);

        $response->assertStatus(200);
        $content = $response->json();
        $this->assertSame(0, $content['success']);
        $this->assertSame(1, $content['faillure']);
        $this->assertStringContainsString("Token with jti: {$jti} not valid", $content['raison']);
    }

    public function test_payment_endpoint_blocks_revoked_bearer_token(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Revoked Token',
            'scopes' => '[]',
            'revoked' => true,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $jwtPayload = base64_encode(json_encode([
            'sub' => 'user-test',
            'jti' => $jti,
        ]));
        $fakeBearer = "header.{$jwtPayload}.sig";

        $response = $this->withHeader('Authorization', 'Bearer ' . $fakeBearer)
            ->postJson('/api/payments', []);

        $response->assertStatus(200);
        $content = $response->json();
        $this->assertSame(0, $content['success']);
        $this->assertSame(1, $content['faillure']);
        $this->assertStringContainsString("Token with jti: {$jti} not valid", $content['raison']);
    }
}
