<?php

namespace Tests\Unit;

use App\Domain\Model\AccessToken;
use App\Http\Middleware\ControlAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlAccessMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private ControlAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ControlAccess();
    }

    public function test_check_access_token_validity_with_valid_token(): void
    {
        $token = new AccessToken([
            'expires_at' => Carbon::now()->addHour(),
            'revoked' => false,
        ]);

        $this->assertTrue($this->middleware->checkAccessTokenValidity($token));
    }

    public function test_check_access_token_validity_with_expired_token(): void
    {
        $token = new AccessToken([
            'expires_at' => Carbon::now()->subMinute(),
            'revoked' => false,
        ]);

        $this->assertFalse($this->middleware->checkAccessTokenValidity($token));
    }

    public function test_check_access_token_validity_with_revoked_token(): void
    {
        $token = new AccessToken([
            'expires_at' => Carbon::now()->addHour(),
            'revoked' => true,
        ]);

        $this->assertFalse($this->middleware->checkAccessTokenValidity($token));
    }

    public function test_handle_passes_request_when_no_bearer_token(): void
    {
        $request = Request::create('/api/payments', 'POST');
        $executed = false;

        $response = $this->middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['success' => 1]);
        });

        $this->assertTrue($executed);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handle_passes_request_when_token_not_found_in_database(): void
    {
        $jti = (string) Str::uuid();
        $jwtPayload = base64_encode(json_encode(['sub' => 'user-1', 'jti' => $jti]));
        $fakeJwt = "fakeheader.{$jwtPayload}.fakesig";

        $request = Request::create('/api/payments', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $fakeJwt);

        $executed = false;
        $response = $this->middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['next' => true]);
        });

        $this->assertTrue($executed);
    }

    public function test_handle_passes_request_when_token_is_valid(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Valid Test Token',
            'scopes' => '[]',
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $jwtPayload = base64_encode(json_encode(['sub' => 'user-1', 'jti' => $jti]));
        $fakeJwt = "fakeheader.{$jwtPayload}.fakesig";

        $request = Request::create('/api/payments', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $fakeJwt);

        $executed = false;
        $response = $this->middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['next' => true]);
        });

        $this->assertTrue($executed);
    }

    public function test_handle_blocks_request_when_token_is_expired(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Expired Test Token',
            'scopes' => '[]',
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $jwtPayload = base64_encode(json_encode(['sub' => 'user-1', 'jti' => $jti]));
        $fakeJwt = "fakeheader.{$jwtPayload}.fakesig";

        $request = Request::create('/api/payments', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $fakeJwt);

        $executed = false;
        $response = $this->middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['next' => true]);
        });

        $this->assertFalse($executed);
        $content = json_decode($response->getContent(), true);
        $this->assertSame(0, $content['success']);
        $this->assertSame(1, $content['faillure']);
        $this->assertStringContainsString("Token with jti: {$jti} not valid", $content['raison']);
    }

    public function test_handle_blocks_request_when_token_is_revoked(): void
    {
        $jti = (string) Str::uuid();
        AccessToken::create([
            'id' => $jti,
            'user_id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'name' => 'Revoked Test Token',
            'scopes' => '[]',
            'revoked' => true,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $jwtPayload = base64_encode(json_encode(['sub' => 'user-1', 'jti' => $jti]));
        $fakeJwt = "fakeheader.{$jwtPayload}.fakesig";

        $request = Request::create('/api/payments', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $fakeJwt);

        $executed = false;
        $response = $this->middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;
            return response()->json(['next' => true]);
        });

        $this->assertFalse($executed);
        $content = json_decode($response->getContent(), true);
        $this->assertSame(0, $content['success']);
        $this->assertSame(1, $content['faillure']);
        $this->assertStringContainsString("Token with jti: {$jti} not valid", $content['raison']);
    }
}
