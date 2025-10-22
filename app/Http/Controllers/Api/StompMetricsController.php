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
        // In production, this would query TR262Service active connections
        $stats = [
            'total_connections' => 0,
            'active_connections' => 0,
            'idle_connections' => 0,
            'failed_connections' => 0,
            'connections_by_device' => [],
            'connections_by_broker' => [],
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get message throughput statistics
     */
    public function throughput()
    {
        $timeRanges = ['5m', '1h', '24h'];
        $throughput = [];
        
        foreach ($timeRanges as $range) {
            $throughput[$range] = [
                'published' => 0,
                'received' => 0,
                'acked' => 0,
                'nacked' => 0,
                'avg_msg_per_sec' => 0,
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $throughput,
        ]);
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
     * Get broker health status
     */
    public function brokerHealth()
    {
        $brokers = [
            [
                'broker_id' => 'broker_1',
                'host' => 'localhost',
                'port' => 61613,
                'status' => 'healthy',
                'uptime_seconds' => 86400,
                'active_connections' => 0,
                'message_rate' => 0,
                'last_check' => now()->toIso8601String(),
            ],
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $brokers,
        ]);
    }

    private function getMetrics(string $timeRange): array
    {
        $interval = $this->getTimeInterval($timeRange);
        
        $metrics = DB::table('system_telemetry')
            ->where('metric_name', 'like', 'stomp_%')
            ->where('collected_at', '>=', now()->sub($interval))
            ->orderBy('collected_at', 'desc')
            ->get();
        
        return [
            'current' => [
                'connections' => 0,
                'messages_per_second' => 0,
                'avg_latency_ms' => 0,
            ],
            'historical' => $metrics,
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
