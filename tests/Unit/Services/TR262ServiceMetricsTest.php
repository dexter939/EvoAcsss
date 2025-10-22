<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Monitoring\StompMetricsCollector;
use Illuminate\Support\Facades\DB;

class TR262ServiceMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset all counters to zero for each test
        StompMetricsCollector::resetAll();
    }

    public function test_metrics_collector_increments_on_publish()
    {
        $initialPublished = StompMetricsCollector::get('messages_published');
        
        StompMetricsCollector::increment('messages_published');
        
        $afterPublish = StompMetricsCollector::get('messages_published');
        
        $this->assertEquals($initialPublished + 1, $afterPublish);
    }

    public function test_metrics_collector_increments_on_ack()
    {
        $initialAcked = StompMetricsCollector::get('messages_acked');
        
        StompMetricsCollector::increment('messages_acked');
        
        $afterAck = StompMetricsCollector::get('messages_acked');
        
        $this->assertEquals($initialAcked + 1, $afterAck);
    }

    public function test_metrics_collector_increments_on_nack()
    {
        $initialNacked = StompMetricsCollector::get('messages_nacked');
        
        StompMetricsCollector::increment('messages_nacked');
        
        $afterNack = StompMetricsCollector::get('messages_nacked');
        
        $this->assertEquals($initialNacked + 1, $afterNack);
    }

    public function test_connection_counters_increment_and_decrement()
    {
        $this->assertEquals(0, StompMetricsCollector::get('connections_total'));
        $this->assertEquals(0, StompMetricsCollector::get('connections_active'));
        
        StompMetricsCollector::increment('connections_total');
        StompMetricsCollector::increment('connections_active');
        
        $this->assertEquals(1, StompMetricsCollector::get('connections_total'));
        $this->assertEquals(1, StompMetricsCollector::get('connections_active'));
        
        StompMetricsCollector::decrement('connections_active');
        
        $this->assertEquals(1, StompMetricsCollector::get('connections_total'));
        $this->assertEquals(0, StompMetricsCollector::get('connections_active'));
    }

    public function test_transaction_counters()
    {
        StompMetricsCollector::increment('transactions_begun');
        $this->assertEquals(1, StompMetricsCollector::get('transactions_begun'));
        
        StompMetricsCollector::increment('transactions_committed');
        $this->assertEquals(1, StompMetricsCollector::get('transactions_committed'));
        
        StompMetricsCollector::increment('transactions_aborted');
        $this->assertEquals(1, StompMetricsCollector::get('transactions_aborted'));
    }

    public function test_error_counters()
    {
        StompMetricsCollector::increment('errors_connection');
        $this->assertEquals(1, StompMetricsCollector::get('errors_connection'));
        
        StompMetricsCollector::increment('errors_publish');
        $this->assertEquals(1, StompMetricsCollector::get('errors_publish'));
        
        StompMetricsCollector::increment('errors_subscribe');
        $this->assertEquals(1, StompMetricsCollector::get('errors_subscribe'));
    }

    public function test_publish_latency_tracking()
    {
        StompMetricsCollector::recordTiming('publish', 10.5);
        StompMetricsCollector::recordTiming('publish', 20.5);
        
        $avgLatency = StompMetricsCollector::getAverageTiming('publish');
        
        $this->assertEquals(15.5, $avgLatency);
    }

    public function test_ack_latency_tracking()
    {
        StompMetricsCollector::recordTiming('ack', 5.0);
        StompMetricsCollector::recordTiming('ack', 10.0);
        StompMetricsCollector::recordTiming('ack', 15.0);
        
        $avgLatency = StompMetricsCollector::getAverageTiming('ack');
        
        $this->assertEquals(10.0, $avgLatency);
    }

    public function test_full_message_lifecycle_tracking()
    {
        StompMetricsCollector::increment('messages_published');
        StompMetricsCollector::increment('messages_received');
        StompMetricsCollector::increment('messages_acked');
        
        $stats = StompMetricsCollector::getAll();
        
        $this->assertEquals(1, $stats['messages_published']);
        $this->assertEquals(1, $stats['messages_received']);
        $this->assertEquals(1, $stats['messages_acked']);
        $this->assertEquals(0, $stats['messages_nacked']);
    }

    public function test_complete_transaction_workflow()
    {
        StompMetricsCollector::increment('transactions_begun');
        StompMetricsCollector::increment('messages_published');
        StompMetricsCollector::increment('messages_published');
        StompMetricsCollector::increment('transactions_committed');
        
        $stats = StompMetricsCollector::getAll();
        
        $this->assertEquals(1, $stats['transactions_begun']);
        $this->assertEquals(2, $stats['messages_published']);
        $this->assertEquals(1, $stats['transactions_committed']);
        $this->assertEquals(0, $stats['transactions_aborted']);
    }
}
