<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TR262Service;

/**
 * Collect STOMP Connection Metrics for TR-262
 * 
 * Collects and persists metrics about active STOMP connections,
 * message throughput, and broker health.
 */
class CollectStompMetrics extends Command
{
    protected $signature = 'metrics:stomp {--store : Store metrics in database}';
    protected $description = 'Collect TR-262 STOMP connection metrics';

    public function handle()
    {
        $metrics = $this->collectMetrics();
        
        if ($this->option('store')) {
            $this->storeMetrics($metrics);
        }
        
        $this->displayMetrics($metrics);
        
        return 0;
    }

    private function collectMetrics(): array
    {
        // Get latest snapshot from database (updated by PollBrokerMetrics)
        $latest = \App\Models\StompMetric::getLatest();
        
        if (!$latest) {
            // Fallback to Redis counters if no DB data yet
            $stats = \App\Services\TR262Service::getGlobalStats();
            
            return [
                'timestamp' => now()->toIso8601String(),
                'connections' => [
                    'total' => $stats['connections_total'] ?? 0,
                    'active' => $stats['connections_active'] ?? 0,
                    'idle' => 0,
                    'failed' => $stats['errors_connection'] ?? 0,
                ],
                'messages' => [
                    'published_total' => $stats['messages_published'] ?? 0,
                    'received_total' => $stats['messages_received'] ?? 0,
                    'acked_total' => $stats['messages_acked'] ?? 0,
                    'nacked_total' => $stats['messages_nacked'] ?? 0,
                    'pending_ack' => 0,
                ],
                'subscriptions' => [
                    'total' => 0,
                    'active' => 0,
                ],
                'transactions' => [
                    'begun' => $stats['transactions_begun'] ?? 0,
                    'committed' => $stats['transactions_committed'] ?? 0,
                    'aborted' => $stats['transactions_aborted'] ?? 0,
                ],
                'performance' => [
                    'avg_publish_time_ms' => 0,
                    'avg_ack_time_ms' => 0,
                    'messages_per_second' => 0,
                ],
                'errors' => [
                    'connection_failures' => $stats['errors_connection'] ?? 0,
                    'publish_failures' => $stats['errors_publish'] ?? 0,
                    'subscribe_failures' => $stats['errors_subscribe'] ?? 0,
                    'timeout_errors' => 0,
                ],
            ];
        }
        
        return [
            'timestamp' => $latest->collected_at->toIso8601String(),
            'connections' => [
                'total' => $latest->connections_total,
                'active' => $latest->connections_active,
                'idle' => $latest->connections_idle,
                'failed' => $latest->connections_failed,
            ],
            'messages' => [
                'published_total' => $latest->messages_published,
                'received_total' => $latest->messages_received,
                'acked_total' => $latest->messages_acked,
                'nacked_total' => $latest->messages_nacked,
                'pending_ack' => $latest->messages_pending_ack,
            ],
            'subscriptions' => [
                'total' => $latest->subscriptions_total,
                'active' => $latest->subscriptions_active,
            ],
            'transactions' => [
                'begun' => $latest->transactions_begun,
                'committed' => $latest->transactions_committed,
                'aborted' => $latest->transactions_aborted,
            ],
            'performance' => [
                'avg_publish_time_ms' => (float) $latest->avg_publish_latency_ms,
                'avg_ack_time_ms' => (float) $latest->avg_ack_latency_ms,
                'messages_per_second' => (float) $latest->messages_per_second,
            ],
            'errors' => [
                'connection_failures' => $latest->errors_connection,
                'publish_failures' => $latest->errors_publish,
                'subscribe_failures' => $latest->errors_subscribe,
                'timeout_errors' => $latest->errors_timeout,
            ],
        ];
    }

    private function storeMetrics(array $metrics): void
    {
        try {
            DB::table('system_telemetry')->insert([
                'metric_name' => 'stomp_connections',
                'metric_value' => $metrics['connections']['total'],
                'metric_type' => 'gauge',
                'collected_at' => now(),
                'metadata' => json_encode($metrics),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->info('✓ Metrics stored successfully');
        } catch (\Exception $e) {
            $this->error('Failed to store metrics: ' . $e->getMessage());
        }
    }

    private function displayMetrics(array $metrics): void
    {
        $this->info('=== TR-262 STOMP Metrics ===');
        $this->line('');
        
        $this->info('Connections:');
        $this->line("  Total: {$metrics['connections']['total']}");
        $this->line("  Active: {$metrics['connections']['active']}");
        $this->line("  Idle: {$metrics['connections']['idle']}");
        $this->line("  Failed: {$metrics['connections']['failed']}");
        $this->line('');
        
        $this->info('Messages:');
        $this->line("  Published: {$metrics['messages']['published_total']}");
        $this->line("  Received: {$metrics['messages']['received_total']}");
        $this->line("  Acknowledged: {$metrics['messages']['acked_total']}");
        $this->line("  Rejected: {$metrics['messages']['nacked_total']}");
        $this->line("  Pending ACK: {$metrics['messages']['pending_ack']}");
        $this->line('');
        
        $this->info('Performance:');
        $this->line("  Avg Publish Time: {$metrics['performance']['avg_publish_time_ms']}ms");
        $this->line("  Throughput: {$metrics['performance']['messages_per_second']} msg/s");
        $this->line('');
        
        $this->info('Errors:');
        $this->line("  Connection Failures: {$metrics['errors']['connection_failures']}");
        $this->line("  Publish Failures: {$metrics['errors']['publish_failures']}");
        $this->line("  Timeouts: {$metrics['errors']['timeout_errors']}");
    }
}
