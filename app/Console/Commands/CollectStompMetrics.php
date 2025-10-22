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
        // In a real implementation, you'd track these via the TR262Service
        // For now, we provide a structure for monitoring
        
        return [
            'timestamp' => now()->toIso8601String(),
            'connections' => [
                'total' => 0, // Would query active connections
                'active' => 0,
                'idle' => 0,
                'failed' => 0,
            ],
            'messages' => [
                'published_total' => 0,
                'received_total' => 0,
                'acked_total' => 0,
                'nacked_total' => 0,
                'pending_ack' => 0,
            ],
            'subscriptions' => [
                'total' => 0,
                'active' => 0,
            ],
            'transactions' => [
                'begun' => 0,
                'committed' => 0,
                'aborted' => 0,
            ],
            'performance' => [
                'avg_publish_time_ms' => 0,
                'avg_ack_time_ms' => 0,
                'messages_per_second' => 0,
            ],
            'errors' => [
                'connection_failures' => 0,
                'publish_failures' => 0,
                'subscribe_failures' => 0,
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
