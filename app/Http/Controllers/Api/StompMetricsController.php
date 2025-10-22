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
        $globalStats = \App\Services\TR262Service::getGlobalStats();
        
        $stats = [
            'total_connections' => $globalStats['connections_total'],
            'active_connections' => $globalStats['connections_active'],
            'idle_connections' => max(0, $globalStats['connections_total'] - $globalStats['connections_active']),
            'failed_connections' => $globalStats['errors_connection'],
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
        $globalStats = \App\Services\TR262Service::getGlobalStats();
        
        $timeRanges = ['5m', '1h', '24h'];
        $throughput = [];
        
        // For now, use current totals for all ranges
        // In production, would need historical data
        foreach ($timeRanges as $range) {
            $throughput[$range] = [
                'published' => $globalStats['messages_published'],
                'received' => $globalStats['messages_received'],
                'acked' => $globalStats['messages_acked'],
                'nacked' => $globalStats['messages_nacked'],
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
        $globalStats = \App\Services\TR262Service::getGlobalStats();
        
        $brokers = [
            [
                'broker_id' => 'primary_broker',
                'host' => config('stomp.host', 'localhost'),
                'port' => config('stomp.port', 61613),
                'status' => $globalStats['connections_active'] > 0 ? 'healthy' : 'idle',
                'uptime_seconds' => 0, // Would need to track broker start time
                'active_connections' => $globalStats['connections_active'],
                'message_rate' => $globalStats['messages_published'] + $globalStats['messages_received'],
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
        $globalStats = \App\Services\TR262Service::getGlobalStats();
        
        $metrics = DB::table('system_telemetry')
            ->where('metric_name', 'like', 'stomp_%')
            ->where('collected_at', '>=', now()->sub($interval))
            ->orderBy('collected_at', 'desc')
            ->get();
        
        return [
            'current' => [
                'connections' => $globalStats['connections_active'],
                'messages_per_second' => 0, // Would need timing data
                'avg_latency_ms' => 0,
                'total_published' => $globalStats['messages_published'],
                'total_received' => $globalStats['messages_received'],
                'total_errors' => $globalStats['errors_connection'] + $globalStats['errors_publish'],
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
