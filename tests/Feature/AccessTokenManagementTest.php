<?php

namespace Tests\Feature;

use App\Domain\Model\AccessToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CheckToken;
use Tests\TestCase;

class AccessTokenManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_save_access_token_successfully(): void
    {
        $jti = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $clientId = (string) Str::uuid();
        $expiresAtTimestamp = Carbon::now()->addHour()->timestamp;

        $jwtPayload = base64_encode(json_encode([
            'aud' => $clientId,
            'jti' => $jti,
            'sub' => $userId,
            'scopes' => ['read', 'write'],
            'exp' => $expiresAtTimestamp,
        ]));

        $accessTokenJwt = "header.{$jwtPayload}.signature";

        $tokenData = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => $accessTokenJwt,
        ];

        // The endpoint does: json_decode(json_decode($request->getContent(), true), true)
        $doubleEncodedBody = json_encode(json_encode($tokenData));

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/access-tokens',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                $doubleEncodedBody
            );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Access Token saved successfully',
        ]);

        $this->assertDatabaseHas('access_tokens', [
            'id' => $jti,
            'user_id' => $userId,
            'client_id' => $clientId,
            'token_type' => 'Bearer',
            'revoked' => false,
        ]);
    }

    public function test_logout_revokes_existing_access_token(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Active Token',
            'scopes' => '[]',
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $payload = json_encode(['jti' => $jti]);

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/logout',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                $payload
            );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Access Token revoked successfully',
        ]);

        $this->assertDatabaseHas('access_tokens', [
            'id' => $jti,
            'revoked' => true,
        ]);
    }

    public function test_logout_returns_failure_when_token_not_found(): void
    {
        $nonExistentJti = (string) Str::uuid();
        $payload = json_encode(['jti' => $nonExistentJti]);

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/logout',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                $payload
            );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => "Token with jti: {$nonExistentJti} not found",
        ]);
    }
}
