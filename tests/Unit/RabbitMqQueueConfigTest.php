<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMqQueueConfigTest extends TestCase
{
    #[Test]
    public function rabbitmq_queue_connection_is_configured(): void
    {
        $connection = config('queue.connections.rabbitmq');
        $expectedHost = env('RABBITMQ_HOST', '127.0.0.1');
        $expectedPort = (int) env('RABBITMQ_PORT', 5672);

        $this->assertIsArray($connection);
        $this->assertSame('rabbitmq', $connection['driver']);
        $this->assertSame('default', $connection['queue']);
        $this->assertIsArray($connection['hosts']);
        $this->assertSame($expectedHost, $connection['hosts'][0]['host']);
        $this->assertSame($expectedPort, $connection['hosts'][0]['port']);
    }
}
