<?php

namespace Tests\Unit;

use App\Jobs\ProcessMessages;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessMessagesTest extends TestCase
{
    public function test_process_messages_job_dispatch(): void
    {
        Queue::fake();

        $exchange = 'TEST_EXCHANGE';
        $type = 'fanout';
        $payload = json_encode(['event' => 'payment.created', 'id' => 123]);

        ProcessMessages::dispatch($exchange, $type, $payload);

        Queue::assertPushed(ProcessMessages::class, function ($job) use ($exchange, $type, $payload) {
            $reflector = new \ReflectionClass($job);

            $exchangeProp = $reflector->getProperty('exchangeName');
            $exchangeProp->setAccessible(true);

            $typeProp = $reflector->getProperty('exchangetype');
            $typeProp->setAccessible(true);

            $msgProp = $reflector->getProperty('message');
            $msgProp->setAccessible(true);

            return $exchangeProp->getValue($job) === $exchange
                && $typeProp->getValue($job) === $type
                && $msgProp->getValue($job) === $payload;
        });
    }

    public function test_process_messages_job_instantiation_and_properties(): void
    {
        $job = new ProcessMessages('EXCHANGE_A', 'direct', 'test-message');

        $reflector = new \ReflectionClass($job);
        $exchangeProp = $reflector->getProperty('exchangeName');
        $exchangeProp->setAccessible(true);

        $typeProp = $reflector->getProperty('exchangetype');
        $typeProp->setAccessible(true);

        $msgProp = $reflector->getProperty('message');
        $msgProp->setAccessible(true);

        $this->assertSame('EXCHANGE_A', $exchangeProp->getValue($job));
        $this->assertSame('direct', $typeProp->getValue($job));
        $this->assertSame('test-message', $msgProp->getValue($job));
    }
}
