<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RabbitMqQueueStatsService
{
    /**
     * @return array<string, int|string>
     */
    public function stats(string $queueName): array
    {
        $managementConfig = (array) config('queue.connections.rabbitmq.management', []);
        $host = (string) ($managementConfig['host'] ?? '127.0.0.1');
        $port = (int) ($managementConfig['port'] ?? 15672);
        $user = (string) ($managementConfig['user'] ?? 'guest');
        $password = (string) ($managementConfig['password'] ?? 'guest');
        $vhost = rawurlencode((string) config('queue.connections.rabbitmq.hosts.0.vhost', '/'));
        $queue = rawurlencode($queueName);

        $response = Http::acceptJson()
            ->withBasicAuth($user, $password)
            ->timeout(5)
            ->get("http://{$host}:{$port}/api/queues/{$vhost}/{$queue}");

        if ($response->failed()) {
            $errorMessage = (string) data_get($response->json(), 'reason', 'Failed to fetch RabbitMQ queue stats.');
            throw new RuntimeException($errorMessage);
        }

        $payload = (array) $response->json();
        $ready = (int) data_get($payload, 'messages_ready', 0);
        $unacked = (int) data_get($payload, 'messages_unacknowledged', 0);
        $total = (int) data_get($payload, 'messages', $ready + $unacked);
        $consumers = (int) data_get($payload, 'consumers', 0);
        $drainProgress = $total <= 0
            ? 100
            : (int) min(100, floor((($total - $ready) / $total) * 100));

        return [
            'queue' => $queueName,
            'messages_ready' => $ready,
            'messages_unacknowledged' => $unacked,
            'messages_total' => $total,
            'consumers' => $consumers,
            'drain_progress_percentage' => $drainProgress,
        ];
    }
}
