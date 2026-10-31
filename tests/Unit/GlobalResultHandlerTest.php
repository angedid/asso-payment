<?php

namespace Tests\Unit;

use App\Http\Controllers\GlobalResultHandler;
use PHPUnit\Framework\TestCase;

class GlobalResultHandlerTest extends TestCase
{
    public function test_build_success_response_array(): void
    {
        $payload = ['item' => 'data', 'id' => 123];
        $result = GlobalResultHandler::buildSuccesResponseArray($payload);

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['faillure']);
        $this->assertSame($payload, $result['response']);
    }

    public function test_build_failure_reason_array(): void
    {
        $reason = 'Unauthorized access to requested resource';
        $result = GlobalResultHandler::buildFaillureReasonArray($reason);

        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['faillure']);
        $this->assertSame($reason, $result['raison']);
    }
}
