<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * STOMP Metrics API Controller
 * 
 * Provides REST API endpoints for TR-262 STOMP monitoring
 */
class StompMetricsController extends Controller
{
    /**
     * Get current STOMP metrics
     */
    public function index(Request $request)
    {
        $timeRange = $request->input('time_range', '1h'); // 1h, 24h, 7d
        
        $metrics = $this->getMetrics($timeRange);
        
        return response()->json([
            'status' => 'success',
            'time_range' => $timeRange,
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get STOMP connection statistics
     */
    public function connections()
    {
        $latest = \App\Models\StompMetric::getLatest();
        
        $stats = [
            'total_connections' => $latest->connections_total ?? 0,
            'active_connections' => $latest->connections_active ?? 0,
            'idle_connections' => $latest->connections_idle ?? 0,
            'failed_connections' => $latest->connections_failed ?? 0,
            'connections_by_device' => [],
            'connections_by_broker' => $latest->broker_stats['overview']['connections'] ?? 0,
            'broker_info' => $latest->broker_stats ?? null,
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'timestamp' => $latest->collected_at ?? now(),
        ]);
    }

    /**
     * Get message throughput statistics with time-windowed rates
     */
    public function throughput()
    {
        $throughput = [
            '5m' => $this->getThroughputForRange(new \DateInterval('PT5M')),
            '1h' => $this->getThroughputForRange(new \DateInterval('PT1H')),
            '24h' => $this->getThroughputForRange(new \DateInterval('P1D')),
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $throughput,
        ]);
    }
    
    private function getThroughputForRange(\DateInterval $interval): array
    {
        $metrics = \App\Models\StompMetric::getMetricsForRange($interval);
        
        if ($metrics->isEmpty()) {
            return [
                'published' => 0,
                'received' => 0,
                'acked' => 0,
                'nacked' => 0,
                'avg_msg_per_sec' => 0.0,
            ];
        }
        
        $latest = $metrics->first();
        $messageRate = \App\Models\StompMetric::calculateMessageRate($interval);
        
        return [
            'published' => $latest->messages_published,
            'received' => $latest->messages_received,
            'acked' => $latest->messages_acked,
            'nacked' => $latest->messages_nacked,
            'avg_msg_per_sec' => $messageRate,
        ];
    }

    /**
     * Get STOMP error statistics
     */
    public function errors()
    {
        $errors = DB::table('system_telemetry')
            ->where('metric_name', 'like', 'stomp_error_%')
            ->where('collected_at', '>=', now()->subDay())
            ->orderBy('collected_at', 'desc')
            ->limit(100)
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $errors,
        ]);
    }

    /**
     * Get broker health status from latest metrics
     */
    public function brokerHealth()
    {
        $latest = \App\Models\StompMetric::getLatest();
        
        if (!$latest || !$latest->broker_stats) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'broker_id' => 'primary_broker',
                    'host' => config('stomp.rabbitmq.management_host', 'localhost'),
                    'port' => config('stomp.port', 61613),
                    'status' => 'unknown',
                    'active_connections' => 0,
                    'message_rate' => 0.0,
                    'last_check' => now()->toIso8601String(),
                ],
            ]);
        }
        
        $overview = $latest->broker_stats['overview'] ?? [];
        $rates = $latest->broker_stats['rates'] ?? [];
        $nodes = $latest->broker_stats['nodes'] ?? [];
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'broker_id' => 'rabbitmq_primary',
                'host' => config('stomp.rabbitmq.management_host', 'localhost'),
                'port' => config('stomp.port', 61613),
                'status' => 'healthy',
                'active_connections' => $overview['connections'] ?? 0,
                'channels' => $overview['channels'] ?? 0,
                'queues' => $overview['queues'] ?? 0,
                'consumers' => $overview['consumers'] ?? 0,
                'messages_ready' => $overview['messages_ready'] ?? 0,
                'messages_unacknowledged' => $overview['messages_unacknowledged'] ?? 0,
                'publish_rate' => $rates['publish_rate'] ?? 0.0,
                'deliver_rate' => $rates['deliver_rate'] ?? 0.0,
                'ack_rate' => $rates['ack_rate'] ?? 0.0,
                'nodes' => count($nodes),
                'last_check' => $latest->collected_at->toIso8601String(),
            ],
        ]);
    }

    private function getMetrics(string $timeRange): array
    {
        $interval = $this->getTimeInterval($timeRange);
        $latest = \App\Models\StompMetric::getLatest();
        
        $historical = \App\Models\StompMetric::getMetricsForRange($interval);
        
        return [
            'current' => [
                'connections' => $latest->connections_active ?? 0,
                'messages_per_second' => $latest->messages_per_second ?? 0.0,
                'avg_publish_latency_ms' => $latest->avg_publish_latency_ms ?? 0.0,
                'avg_ack_latency_ms' => $latest->avg_ack_latency_ms ?? 0.0,
                'total_published' => $latest->messages_published ?? 0,
                'total_received' => $latest->messages_received ?? 0,
                'total_errors' => ($latest->errors_connection ?? 0) + ($latest->errors_publish ?? 0),
            ],
            'historical' => $historical->map(function($metric) {
                return [
                    'timestamp' => $metric->collected_at->toIso8601String(),
                    'connections_active' => $metric->connections_active,
                    'messages_per_second' => $metric->messages_per_second,
                    'messages_published' => $metric->messages_published,
                    'messages_received' => $metric->messages_received,
                    'avg_publish_latency_ms' => $metric->avg_publish_latency_ms,
                ];
            }),
        ];
    }

    private function getTimeInterval(string $range): \DateInterval
    {
        return match($range) {
            '5m' => new \DateInterval('PT5M'),
            '1h' => new \DateInterval('PT1H'),
            '24h' => new \DateInterval('P1D'),
            '7d' => new \DateInterval('P7D'),
            default => new \DateInterval('PT1H'),
        };
    }
}
