<?php

namespace App\Services;

use App\Contexts\TenantContext;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class TenantAnomalyDetector
{
    protected array $anomalyThresholds;

    public function __construct()
    {
        $this->anomalyThresholds = [
            'failed_auth_attempts' => config('tenant.security.failed_auth_threshold', 5),
            'rate_limit_violations' => config('tenant.security.rate_limit_threshold', 10),
            'cross_tenant_attempts' => config('tenant.security.cross_tenant_threshold', 1),
            'unusual_ip_count' => config('tenant.security.unusual_ip_threshold', 10),
            'detection_window_minutes' => config('tenant.security.detection_window', 15),
        ];
    }

    public function checkTokenAnomaly(?PersonalAccessToken $token, Request $request): void
    {
        if (!$token) {
            return;
        }

        if (!config('tenant.security.anomaly_detection', true)) {
            return;
        }

        $this->checkCrossTenantTokenReuse($token, $request);
        $this->checkTokenIpAnomaly($token, $request);
        $this->checkTokenUsagePattern($token, $request);
    }

    public function checkCrossTenantTokenReuse(PersonalAccessToken $token, Request $request): void
    {
        $requestTenantId = $request->header('X-Tenant-ID');
        $contextTenantId = TenantContext::id();
        $tokenTenantId = $token->tenant_id ?? null;

        $checkAgainstTenant = $requestTenantId ?? $contextTenantId;

        if ($tokenTenantId && $checkAgainstTenant && (int) $tokenTenantId !== (int) $checkAgainstTenant) {
            $this->recordAnomaly('cross_tenant_token_reuse', [
                'token_id' => $token->id,
                'token_tenant_id' => $tokenTenantId,
                'request_tenant_id' => $checkAgainstTenant,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);

            $this->incrementAnomalyCounter('cross_tenant_attempts', $tokenTenantId);

            if ($this->shouldBlockRequest('cross_tenant_attempts', $tokenTenantId)) {
                abort(403, 'Security violation detected');
            }
        }
    }

    public function checkTokenIpAnomaly(PersonalAccessToken $token, Request $request): void
    {
        $currentIp = $request->ip();
        $cacheKey = "token:{$token->id}:known_ips";
        
        $knownIps = Cache::get($cacheKey, []);
        
        if (!in_array($currentIp, $knownIps)) {
            $knownIps[] = $currentIp;
            Cache::put($cacheKey, $knownIps, now()->addDays(7));

            if (count($knownIps) > $this->anomalyThresholds['unusual_ip_count']) {
                $this->recordAnomaly('unusual_ip_diversity', [
                    'token_id' => $token->id,
                    'ip_count' => count($knownIps),
                    'current_ip' => $currentIp,
                    'threshold' => $this->anomalyThresholds['unusual_ip_count'],
                ]);
            }
        }
    }

    public function checkTokenUsagePattern(PersonalAccessToken $token, Request $request): void
    {
        $cacheKey = "token:{$token->id}:usage_pattern";
        $window = now()->subMinutes($this->anomalyThresholds['detection_window_minutes']);
        
        $usageLog = Cache::get($cacheKey, []);
        $usageLog = array_filter($usageLog, fn($entry) => $entry['timestamp'] > $window->timestamp);
        
        $usageLog[] = [
            'timestamp' => now()->timestamp,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
        ];

        Cache::put($cacheKey, $usageLog, now()->addHour());

        $uniqueIps = count(array_unique(array_column($usageLog, 'ip')));
        if ($uniqueIps >= 3 && count($usageLog) > 20) {
            $this->recordAnomaly('rapid_ip_switching', [
                'token_id' => $token->id,
                'unique_ips' => $uniqueIps,
                'request_count' => count($usageLog),
                'window_minutes' => $this->anomalyThresholds['detection_window_minutes'],
            ]);
        }
    }

    public function recordFailedAuthAttempt(Request $request, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::id();
        
        $this->recordAnomaly('failed_auth_attempt', [
            'tenant_id' => $tenantId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
        ]);

        $this->incrementAnomalyCounter('failed_auth_attempts', $tenantId, $request->ip());

        if ($this->shouldBlockRequest('failed_auth_attempts', $tenantId, $request->ip())) {
            $this->recordAnomaly('auth_blocked', [
                'tenant_id' => $tenantId,
                'ip_address' => $request->ip(),
                'reason' => 'Too many failed authentication attempts',
            ]);
        }
    }

    public function recordRateLimitViolation(Request $request, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::id();

        $this->recordAnomaly('rate_limit_violation', [
            'tenant_id' => $tenantId,
            'ip_address' => $request->ip(),
            'path' => $request->path(),
        ]);

        $this->incrementAnomalyCounter('rate_limit_violations', $tenantId, $request->ip());
    }

    protected function incrementAnomalyCounter(string $type, ?int $tenantId, ?string $ip = null): void
    {
        $suffix = $ip ? ":{$ip}" : '';
        $cacheKey = "anomaly:{$type}:{$tenantId}{$suffix}";
        $window = $this->anomalyThresholds['detection_window_minutes'] * 60;

        $count = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $count + 1, now()->addSeconds($window));
    }

    protected function shouldBlockRequest(string $type, ?int $tenantId, ?string $ip = null): bool
    {
        $suffix = $ip ? ":{$ip}" : '';
        $cacheKey = "anomaly:{$type}:{$tenantId}{$suffix}";
        
        $count = Cache::get($cacheKey, 0);
        $threshold = $this->anomalyThresholds[$type] ?? 10;

        return $count >= $threshold;
    }

    protected function recordAnomaly(string $type, array $data): void
    {
        $logData = array_merge($data, [
            'anomaly_type' => $type,
            'detected_at' => now()->toIso8601String(),
            'severity' => $this->getAnomalySeverity($type),
        ]);

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            $severity = $logData['severity'];
            
            match ($severity) {
                'critical' => Log::channel($channel)->critical("Security anomaly detected: {$type}", $logData),
                'high' => Log::channel($channel)->error("Security anomaly detected: {$type}", $logData),
                'medium' => Log::channel($channel)->warning("Security anomaly detected: {$type}", $logData),
                default => Log::channel($channel)->info("Security anomaly detected: {$type}", $logData),
            };
        } catch (\Throwable $e) {
            Log::warning("Security anomaly detected: {$type}", $logData);
        }

        if (config('tenant.security.alert_on_anomaly', true)) {
            $this->dispatchSecurityAlert($type, $logData);
        }
    }

    protected function getAnomalySeverity(string $type): string
    {
        return match ($type) {
            'cross_tenant_token_reuse' => 'critical',
            'auth_blocked' => 'critical',
            'rapid_ip_switching' => 'high',
            'failed_auth_attempt' => 'medium',
            'rate_limit_violation' => 'medium',
            'unusual_ip_diversity' => 'low',
            default => 'low',
        };
    }

    protected function dispatchSecurityAlert(string $type, array $data): void
    {
        $severity = $data['severity'] ?? 'low';
        
        if (in_array($severity, ['critical', 'high'])) {
            try {
                app(SecurityAlertService::class)->dispatch($type, $data);
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch security alert', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
