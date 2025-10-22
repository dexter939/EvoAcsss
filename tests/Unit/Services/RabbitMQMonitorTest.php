<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Monitoring\RabbitMQMonitor;
use App\Services\Monitoring\StompMetricsCollector;
use GuzzleHttp\Exception\ConnectException;

class RabbitMQMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        StompMetricsCollector::resetAll();
    }

    public function test_health_check_increments_unavailable_counter_on_connection_error()
    {
        $initialCount = StompMetricsCollector::get('errors_broker_unavailable');
        
        $monitor = new RabbitMQMonitor();
        $isHealthy = $monitor->isHealthy();
        
        $this->assertFalse($isHealthy);
        $this->assertGreaterThan($initialCount, StompMetricsCollector::get('errors_broker_unavailable'));
    }

    public function test_get_connections_increments_unavailable_counter_on_failure()
    {
        $initialCount = StompMetricsCollector::get('errors_broker_unavailable');
        
        $monitor = new RabbitMQMonitor();
        $connections = $monitor->getConnections();
        
        $this->assertIsArray($connections);
        $this->assertEmpty($connections);
        $this->assertGreaterThan($initialCount, StompMetricsCollector::get('errors_broker_unavailable'));
    }

    public function test_get_queues_increments_unavailable_counter_on_failure()
    {
        $initialCount = StompMetricsCollector::get('errors_broker_unavailable');
        
        $monitor = new RabbitMQMonitor();
        $queues = $monitor->getQueues();
        
        $this->assertIsArray($queues);
        $this->assertEmpty($queues);
        $this->assertGreaterThan($initialCount, StompMetricsCollector::get('errors_broker_unavailable'));
    }

    public function test_get_nodes_increments_unavailable_counter_on_failure()
    {
        $initialCount = StompMetricsCollector::get('errors_broker_unavailable');
        
        $monitor = new RabbitMQMonitor();
        $nodes = $monitor->getNodes();
        
        $this->assertIsArray($nodes);
        $this->assertEmpty($nodes);
        $this->assertGreaterThan($initialCount, StompMetricsCollector::get('errors_broker_unavailable'));
    }

    public function test_get_message_rates_returns_zeros_on_broker_unavailable()
    {
        $monitor = new RabbitMQMonitor();
        $rates = $monitor->getMessageRates();
        
        $this->assertIsArray($rates);
        $this->assertEquals(0.0, $rates['publish_rate'] ?? 0.0);
        $this->assertEquals(0.0, $rates['deliver_rate'] ?? 0.0);
    }

    public function test_get_broker_stats_returns_safe_defaults_on_broker_unavailable()
    {
        $initialCount = StompMetricsCollector::get('errors_broker_unavailable');
        
        $monitor = new RabbitMQMonitor();
        $stats = $monitor->getBrokerStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('overview', $stats);
        $this->assertArrayHasKey('nodes', $stats);
        $this->assertArrayHasKey('rates', $stats);
        $this->assertArrayHasKey('healthy', $stats);
        $this->assertArrayHasKey('timestamp', $stats);
        
        $this->assertEmpty($stats['overview']);
        $this->assertEmpty($stats['nodes']);
        $this->assertFalse($stats['healthy']);
        
        $this->assertGreaterThan($initialCount, StompMetricsCollector::get('errors_broker_unavailable'));
    }
}
