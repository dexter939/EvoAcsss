<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CpeDevice;
use App\Models\Alarm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;

class MetricsController extends Controller
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        // Initialize Prometheus registry with Redis storage
        $redisConfig = [
            'host' => config('database.redis.default.host'),
            'port' => config('database.redis.default.port'),
            'password' => config('database.redis.default.password'),
            'database' => config('database.redis.metrics.database', 3),
            'timeout' => 0.1,
        ];
        
        Redis::setDefaultOptions($redisConfig);
        $this->registry = CollectorRegistry::getDefault();
    }

    /**
     * Export Prometheus metrics
     */
    public function index()
    {
        try {
            // Collect all metrics (using gauges only, no wipeStorage)
            $this->collectDeviceMetrics();
            $this->collectSessionMetrics();
            $this->collectQueueMetrics();
            $this->collectAlarmMetrics();
            $this->collectProtocolMetrics();
            $this->collectSystemMetrics();
            
            // Render metrics in Prometheus format
            $renderer = new RenderTextFormat();
            $result = $renderer->render($this->registry->getMetricFamilySamples());
            
            return response($result, 200)
                ->header('Content-Type', RenderTextFormat::MIME_TYPE);
                
        } catch (\Exception $e) {
            \Log::error('Metrics export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('# Error exporting metrics', 500)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Collect CPE device metrics
     */
    private function collectDeviceMetrics(): void
    {
        $namespace = 'acs';
        
        // Total devices
        $totalDevices = CpeDevice::count();
        $gauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_total',
            'Total number of CPE devices'
        );
        $gauge->set($totalDevices);
        
        // Devices by status - reset all known statuses to zero first
        $statusGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_by_status',
            'Number of devices by status',
            ['status']
        );
        
        // Reset all known statuses to zero
        $knownStatuses = ['online', 'offline', 'unknown', 'provisioning', 'error'];
        foreach ($knownStatuses as $status) {
            $statusGauge->set(0, [$status]);
        }
        
        // Set actual values
        $devicesByStatus = CpeDevice::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        
        foreach ($devicesByStatus as $item) {
            $statusGauge->set($item->count, [$item->status ?? 'unknown']);
        }
        
        // Online vs offline devices
        $onlineDevices = CpeDevice::where('status', 'online')->count();
        $offlineDevices = CpeDevice::where('status', 'offline')->count();
        
        $onlineGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_online',
            'Number of online devices'
        );
        $onlineGauge->set($onlineDevices);
        
        $offlineGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_offline',
            'Number of offline devices'
        );
        $offlineGauge->set($offlineDevices);
        
        // Devices by vendor
        $devicesByVendor = CpeDevice::select('manufacturer', DB::raw('count(*) as count'))
            ->whereNotNull('manufacturer')
            ->groupBy('manufacturer')
            ->limit(20)
            ->get();
        
        $vendorGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_by_vendor',
            'Number of devices by vendor',
            ['vendor']
        );
        
        foreach ($devicesByVendor as $item) {
            $vendorGauge->set($item->count, [$item->manufacturer]);
        }
        
        // Devices registered in last 24h
        $recentDevices = CpeDevice::where('created_at', '>=', now()->subDay())->count();
        $recentGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_registered_24h',
            'Devices registered in last 24 hours'
        );
        $recentGauge->set($recentDevices);
    }

    /**
     * Collect TR-069/TR-369 session metrics
     */
    private function collectSessionMetrics(): void
    {
        $namespace = 'acs';
        
        // TR-069 sessions (from sessions table or cache)
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->count();
        
        $sessionsGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'tr069_active_sessions',
            'Number of active TR-069 sessions'
        );
        $sessionsGauge->set($activeSessions);
        
        // Connection requests in last hour
        $connectionRequests = DB::table('connection_requests')
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        $connReqGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'connection_requests_1h',
            'Connection requests in last hour'
        );
        $connReqGauge->set($connectionRequests ?? 0);
    }

    /**
     * Collect Laravel queue metrics
     */
    private function collectQueueMetrics(): void
    {
        $namespace = 'acs';
        
        // Known queues
        $queues = ['default', 'provisioning', 'firmware', 'tr069'];
        
        $pendingGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'queue_jobs_pending',
            'Number of pending jobs in queue',
            ['queue']
        );
        
        $failedGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'queue_jobs_failed',
            'Number of failed jobs',
            ['queue']
        );
        
        // Reset all queues to zero first to handle empty queues
        foreach ($queues as $queue) {
            $pendingGauge->set(0, [$queue]);
            $failedGauge->set(0, [$queue]);
        }
        
        // Set actual values
        foreach ($queues as $queue) {
            // Pending jobs
            $pending = DB::table('jobs')
                ->where('queue', $queue)
                ->count();
            $pendingGauge->set($pending, [$queue]);
            
            // Failed jobs
            $failed = DB::table('failed_jobs')
                ->where('queue', $queue)
                ->count();
            $failedGauge->set($failed, [$queue]);
        }
        
        // Total failed jobs
        $totalFailed = DB::table('failed_jobs')->count();
        $totalFailedGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'queue_jobs_failed_total',
            'Total number of failed jobs'
        );
        $totalFailedGauge->set($totalFailed);
    }

    /**
     * Collect alarm metrics
     */
    private function collectAlarmMetrics(): void
    {
        $namespace = 'acs';
        
        // Active alarms
        $activeAlarms = Alarm::where('status', 'active')->count();
        $activeGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'alarms_active',
            'Number of active alarms'
        );
        $activeGauge->set($activeAlarms);
        
        // Alarms by severity - reset all severities to zero first
        $severityGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'alarms_by_severity',
            'Active alarms by severity',
            ['severity']
        );
        
        // Reset all known severities to zero
        $knownSeverities = ['critical', 'major', 'minor', 'warning', 'info'];
        foreach ($knownSeverities as $severity) {
            $severityGauge->set(0, [$severity]);
        }
        
        // Set actual values
        $alarmsBySeverity = Alarm::select('severity', DB::raw('count(*) as count'))
            ->where('status', 'active')
            ->groupBy('severity')
            ->get();
        
        foreach ($alarmsBySeverity as $item) {
            $severityGauge->set($item->count, [$item->severity]);
        }
        
        // Critical alarms
        $criticalAlarms = Alarm::where('status', 'active')
            ->where('severity', 'critical')
            ->count();
        
        $criticalGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'alarms_critical',
            'Number of critical alarms'
        );
        $criticalGauge->set($criticalAlarms);
    }

    /**
     * Collect protocol-specific metrics
     */
    private function collectProtocolMetrics(): void
    {
        $namespace = 'acs';
        
        // Devices by protocol - reset to zero first
        $protocolGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'devices_by_protocol',
            'Devices by protocol type',
            ['protocol']
        );
        
        // Reset all protocols to zero
        $knownProtocols = ['TR-069', 'TR-369', 'TR-104', 'TR-111'];
        foreach ($knownProtocols as $protocol) {
            $protocolGauge->set(0, [$protocol]);
        }
        
        // Set actual values
        $tr069Devices = CpeDevice::where('protocol', 'TR-069')->count();
        $tr369Devices = CpeDevice::where('protocol', 'TR-369')->count();
        
        $protocolGauge->set($tr069Devices, ['TR-069']);
        $protocolGauge->set($tr369Devices, ['TR-369']);
        
        // USP messages in last hour (if available)
        if (DB::getSchemaBuilder()->hasTable('usp_messages')) {
            $uspMessages = DB::table('usp_messages')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            
            $uspGauge = $this->registry->getOrRegisterGauge(
                $namespace,
                'usp_messages_1h',
                'USP messages processed in last hour'
            );
            $uspGauge->set($uspMessages);
        }
    }

    /**
     * Collect system metrics
     */
    private function collectSystemMetrics(): void
    {
        $namespace = 'acs';
        
        // Database connections
        try {
            $dbConnections = DB::table('pg_stat_activity')
                ->where('datname', config('database.connections.pgsql.database'))
                ->count();
            
            $dbGauge = $this->registry->getOrRegisterGauge(
                $namespace,
                'database_connections',
                'Number of database connections'
            );
            $dbGauge->set($dbConnections);
        } catch (\Exception $e) {
            // Ignore if pg_stat_activity is not accessible
        }
        
        // Cache hit ratio (if using Redis)
        if (config('cache.default') === 'redis') {
            try {
                $redis = app('redis')->connection();
                $info = $redis->info('stats');
                
                if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                    $hits = $info['keyspace_hits'];
                    $misses = $info['keyspace_misses'];
                    $total = $hits + $misses;
                    $hitRatio = $total > 0 ? ($hits / $total) * 100 : 0;
                    
                    $cacheGauge = $this->registry->getOrRegisterGauge(
                        $namespace,
                        'cache_hit_ratio',
                        'Cache hit ratio percentage'
                    );
                    $cacheGauge->set($hitRatio);
                }
            } catch (\Exception $e) {
                // Ignore if Redis stats not available
            }
        }
        
        // Application version
        $versionGauge = $this->registry->getOrRegisterGauge(
            $namespace,
            'app_version',
            'Application version',
            ['version']
        );
        $versionGauge->set(1, [config('app.version', '1.0.0')]);
    }
}
