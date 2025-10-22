<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Monitoring\StompMetricsCollector;
use Illuminate\Support\Facades\DB;

class StompMetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset all counters to zero for each test
        StompMetricsCollector::resetAll();
    }

    public function test_increment_counter()
    {
        $result = StompMetricsCollector::increment('messages_published', 5);
        
        $this->assertEquals(5, $result);
        $this->assertEquals(5, StompMetricsCollector::get('messages_published'));
    }

    public function test_decrement_counter()
    {
        StompMetricsCollector::increment('connections_active', 10);
        $result = StompMetricsCollector::decrement('connections_active', 3);
        
        $this->assertEquals(7, $result);
        $this->assertEquals(7, StompMetricsCollector::get('connections_active'));
    }

    public function test_get_all_counters()
    {
        StompMetricsCollector::increment('messages_published', 10);
        StompMetricsCollector::increment('messages_received', 5);
        StompMetricsCollector::increment('connections_total', 2);
        
        $all = StompMetricsCollector::getAll();
        
        $this->assertIsArray($all);
        $this->assertEquals(10, $all['messages_published']);
        $this->assertEquals(5, $all['messages_received']);
        $this->assertEquals(2, $all['connections_total']);
        $this->assertEquals(0, $all['messages_acked']); // Not incremented
    }

    public function test_reset_counter()
    {
        StompMetricsCollector::increment('messages_published', 100);
        $this->assertEquals(100, StompMetricsCollector::get('messages_published'));
        
        StompMetricsCollector::reset('messages_published');
        
        $this->assertEquals(0, StompMetricsCollector::get('messages_published'));
    }

    public function test_reset_all_counters()
    {
        StompMetricsCollector::increment('messages_published', 10);
        StompMetricsCollector::increment('messages_received', 20);
        StompMetricsCollector::increment('connections_total', 5);
        
        StompMetricsCollector::resetAll();
        
        $all = StompMetricsCollector::getAll();
        
        foreach ($all as $value) {
            $this->assertEquals(0, $value);
        }
    }

    public function test_invalid_counter_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        StompMetricsCollector::increment('invalid_counter');
    }

    public function test_atomic_increment_multiple_operations()
    {
        // Simulate multiple concurrent increments
        for ($i = 0; $i < 10; $i++) {
            StompMetricsCollector::increment('messages_published');
        }
        
        $this->assertEquals(10, StompMetricsCollector::get('messages_published'));
    }

    public function test_timing_metrics()
    {
        StompMetricsCollector::recordTiming('publish', 12.5);
        StompMetricsCollector::recordTiming('publish', 15.0);
        StompMetricsCollector::recordTiming('publish', 10.5);
        
        $avgPublish = StompMetricsCollector::getAverageTiming('publish');
        
        $this->assertEquals(12.67, $avgPublish);
    }

    public function test_timing_metrics_with_no_data()
    {
        $avgAck = StompMetricsCollector::getAverageTiming('ack');
        
        $this->assertEquals(0.0, $avgAck);
    }

    public function test_counter_persistence_across_requests()
    {
        StompMetricsCollector::increment('connections_total', 5);
        
        $value = DB::table('stomp_counters')
            ->where('counter_name', 'connections_total')
            ->value('value');
        
        $this->assertEquals(5, $value);
        
        StompMetricsCollector::increment('connections_total', 3);
        
        $value = DB::table('stomp_counters')
            ->where('counter_name', 'connections_total')
            ->value('value');
        
        $this->assertEquals(8, $value);
    }
}
