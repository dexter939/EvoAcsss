<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Monitoring\RabbitMQMonitor;
use App\Services\Monitoring\StompMetricsCollector;
use App\Models\StompMetric;
use Illuminate\Support\Facades\Log;

class PollBrokerMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stomp:poll-broker
                            {--interval=60 : Polling interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll STOMP broker (RabbitMQ) for real-time metrics and persist to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting STOMP broker metrics polling...');
        
        $monitor = new RabbitMQMonitor();
        
        // Check broker health
        if (!$monitor->isHealthy()) {
            $this->warn('RabbitMQ broker is not accessible. Using local metrics only.');
            $this->collectLocalMetrics();
            return 0;
        }

        $this->info('RabbitMQ broker is healthy. Collecting metrics...');
        
        // Get broker statistics
        $brokerStats = $monitor->getBrokerStats();
        $overview = $brokerStats['overview'] ?? [];
        $rates = $brokerStats['rates'] ?? [];
        
        // Get local Redis counters
        $localStats = StompMetricsCollector::getAll();
        
        // Calculate derived metrics
        $pendingAck = ($localStats['messages_received'] ?? 0) - 
                     ($localStats['messages_acked'] ?? 0) - 
                     ($localStats['messages_nacked'] ?? 0);
        
        // Persist to database
        $metric = StompMetric::create([
            'collected_at' => now(),
            
            // Connection metrics (from broker)
            'connections_total' => $localStats['connections_total'] ?? 0,
            'connections_active' => $overview['connections'] ?? $localStats['connections_active'] ?? 0,
            'connections_idle' => max(0, ($localStats['connections_total'] ?? 0) - ($overview['connections'] ?? 0)),
            'connections_failed' => $localStats['errors_connection'] ?? 0,
            
            // Message metrics (merged local + broker)
            'messages_published' => $localStats['messages_published'] ?? 0,
            'messages_received' => $localStats['messages_received'] ?? 0,
            'messages_acked' => $localStats['messages_acked'] ?? 0,
            'messages_nacked' => $localStats['messages_nacked'] ?? 0,
            'messages_pending_ack' => max(0, $pendingAck),
            
            // Transaction metrics
            'transactions_begun' => $localStats['transactions_begun'] ?? 0,
            'transactions_committed' => $localStats['transactions_committed'] ?? 0,
            'transactions_aborted' => $localStats['transactions_aborted'] ?? 0,
            
            // Subscription metrics (from broker)
            'subscriptions_total' => $overview['consumers'] ?? 0,
            'subscriptions_active' => $overview['consumers'] ?? 0,
            
            // Performance metrics
            'avg_publish_latency_ms' => StompMetricsCollector::getAverageTiming('publish'),
            'avg_ack_latency_ms' => StompMetricsCollector::getAverageTiming('ack'),
            'messages_per_second' => round(($rates['publish_rate'] ?? 0) + ($rates['deliver_rate'] ?? 0), 2),
            
            // Error metrics
            'errors_connection' => $localStats['errors_connection'] ?? 0,
            'errors_publish' => $localStats['errors_publish'] ?? 0,
            'errors_subscribe' => $localStats['errors_subscribe'] ?? 0,
            'errors_timeout' => 0,
            'errors_broker_unavailable' => $localStats['errors_broker_unavailable'] ?? 0,
            'errors_broker_timeout' => $localStats['errors_broker_timeout'] ?? 0,
            
            // Broker statistics (JSON)
            'broker_stats' => [
                'overview' => $overview,
                'rates' => $rates,
                'nodes' => $brokerStats['nodes'] ?? [],
            ],
        ]);

        $this->info("Metrics collected successfully (ID: {$metric->id})");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Active Connections', $metric->connections_active],
                ['Messages Published', $metric->messages_published],
                ['Messages Received', $metric->messages_received],
                ['Messages/sec', $metric->messages_per_second],
                ['Avg Publish Latency (ms)', $metric->avg_publish_latency_ms],
            ]
        );

        return 0;
    }

    /**
     * Collect metrics when broker is unavailable
     */
    private function collectLocalMetrics(): void
    {
        $localStats = StompMetricsCollector::getAll();
        
        $pendingAck = ($localStats['messages_received'] ?? 0) - 
                     ($localStats['messages_acked'] ?? 0) - 
                     ($localStats['messages_nacked'] ?? 0);
        
        StompMetric::create([
            'collected_at' => now(),
            'connections_total' => $localStats['connections_total'] ?? 0,
            'connections_active' => $localStats['connections_active'] ?? 0,
            'connections_idle' => 0,
            'connections_failed' => $localStats['errors_connection'] ?? 0,
            'messages_published' => $localStats['messages_published'] ?? 0,
            'messages_received' => $localStats['messages_received'] ?? 0,
            'messages_acked' => $localStats['messages_acked'] ?? 0,
            'messages_nacked' => $localStats['messages_nacked'] ?? 0,
            'messages_pending_ack' => max(0, $pendingAck),
            'transactions_begun' => $localStats['transactions_begun'] ?? 0,
            'transactions_committed' => $localStats['transactions_committed'] ?? 0,
            'transactions_aborted' => $localStats['transactions_aborted'] ?? 0,
            'subscriptions_total' => 0,
            'subscriptions_active' => 0,
            'avg_publish_latency_ms' => StompMetricsCollector::getAverageTiming('publish'),
            'avg_ack_latency_ms' => StompMetricsCollector::getAverageTiming('ack'),
            'messages_per_second' => 0,
            'errors_connection' => $localStats['errors_connection'] ?? 0,
            'errors_publish' => $localStats['errors_publish'] ?? 0,
            'errors_subscribe' => $localStats['errors_subscribe'] ?? 0,
            'errors_timeout' => 0,
            'errors_broker_unavailable' => $localStats['errors_broker_unavailable'] ?? 0,
            'errors_broker_timeout' => $localStats['errors_broker_timeout'] ?? 0,
            'broker_stats' => null,
        ]);

        $this->info('Local metrics collected (broker unavailable)');
        $this->table(
            ['Error Type', 'Count'],
            [
                ['Broker Unavailable', $localStats['errors_broker_unavailable'] ?? 0],
                ['Broker Timeout', $localStats['errors_broker_timeout'] ?? 0],
                ['Connection Errors', $localStats['errors_connection'] ?? 0],
            ]
        );
    }
}
