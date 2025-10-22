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
        // Get real statistics from TR262Service
        $stats = \App\Services\TR262Service::getGlobalStats();
        
        // Calculate messages per second (simple approximation)
        $messagesPerSecond = 0;
        if ($stats['messages_published'] > 0) {
            // This would need historical data for accurate calculation
            $messagesPerSecond = $stats['messages_published'];
        }
        
        return [
            'timestamp' => now()->toIso8601String(),
            'connections' => [
                'total' => $stats['connections_total'],
                'active' => $stats['connections_active'],
                'idle' => max(0, $stats['connections_total'] - $stats['connections_active']),
                'failed' => $stats['errors_connection'],
            ],
            'messages' => [
                'published_total' => $stats['messages_published'],
                'received_total' => $stats['messages_received'],
                'acked_total' => $stats['messages_acked'],
                'nacked_total' => $stats['messages_nacked'],
                'pending_ack' => $stats['messages_received'] - $stats['messages_acked'] - $stats['messages_nacked'],
            ],
            'subscriptions' => [
                'total' => 0, // Would need to query active service instances
                'active' => 0,
            ],
            'transactions' => [
                'begun' => $stats['transactions_begun'],
                'committed' => $stats['transactions_committed'],
                'aborted' => $stats['transactions_aborted'],
            ],
            'performance' => [
                'avg_publish_time_ms' => 0, // Would need timing instrumentation
                'avg_ack_time_ms' => 0,
                'messages_per_second' => $messagesPerSecond,
            ],
            'errors' => [
                'connection_failures' => $stats['errors_connection'],
                'publish_failures' => $stats['errors_publish'],
                'subscribe_failures' => $stats['errors_subscribe'],
                'timeout_errors' => 0,
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
