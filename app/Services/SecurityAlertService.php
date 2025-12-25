<?php

namespace App\Services;

use App\Contexts\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SecurityAlertService
{
    protected array $alertChannels;
    protected array $severityThresholds;

    public function __construct()
    {
        $this->alertChannels = config('tenant.security.alert_channels', ['log', 'database']);
        $this->severityThresholds = [
            'critical' => ['log', 'database', 'email', 'webhook'],
            'high' => ['log', 'database', 'email'],
            'medium' => ['log', 'database'],
            'low' => ['log'],
        ];
    }

    public function dispatch(string $alertType, array $data): void
    {
        $severity = $data['severity'] ?? 'medium';
        $channels = $this->getChannelsForSeverity($severity);
        
        $alert = $this->createAlert($alertType, $data, $severity);

        foreach ($channels as $channel) {
            try {
                $this->sendToChannel($channel, $alert);
            } catch (\Throwable $e) {
                Log::error("Failed to send security alert to channel: {$channel}", [
                    'alert_type' => $alertType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->trackAlertFrequency($alertType, $severity);
    }

    protected function createAlert(string $type, array $data, string $severity): array
    {
        return [
            'id' => uniqid('alert_', true),
            'type' => $type,
            'severity' => $severity,
            'tenant_id' => $data['tenant_id'] ?? TenantContext::id(),
            'data' => $data,
            'created_at' => now()->toIso8601String(),
            'source' => 'TenantAnomalyDetector',
        ];
    }

    protected function getChannelsForSeverity(string $severity): array
    {
        $configuredChannels = $this->alertChannels;
        $allowedForSeverity = $this->severityThresholds[$severity] ?? ['log'];
        
        return array_intersect($configuredChannels, $allowedForSeverity);
    }

    protected function sendToChannel(string $channel, array $alert): void
    {
        match ($channel) {
            'log' => $this->sendToLog($alert),
            'database' => $this->sendToDatabase($alert),
            'email' => $this->sendToEmail($alert),
            'webhook' => $this->sendToWebhook($alert),
            default => null,
        };
    }

    protected function sendToLog(array $alert): void
    {
        $logData = [
            'alert_id' => $alert['id'],
            'alert_type' => $alert['type'],
            'severity' => $alert['severity'],
            'tenant_id' => $alert['tenant_id'],
            'data' => $alert['data'],
        ];

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            Log::channel($channel)->alert('SECURITY ALERT', $logData);
        } catch (\Throwable $e) {
            Log::alert('SECURITY ALERT', $logData);
        }
    }

    protected function sendToDatabase(array $alert): void
    {
        try {
            \DB::table('security_alerts')->insert([
                'id' => $alert['id'],
                'type' => $alert['type'],
                'severity' => $alert['severity'],
                'tenant_id' => $alert['tenant_id'],
                'data' => json_encode($alert['data']),
                'is_acknowledged' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not store security alert in database', [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendToEmail(array $alert): void
    {
        $recipients = $this->getAlertRecipients($alert);
        
        if (empty($recipients)) {
            return;
        }

        $subject = "[SECURITY ALERT] [{$alert['severity']}] {$alert['type']}";
        $body = $this->formatEmailBody($alert);

        foreach ($recipients as $email) {
            try {
                Mail::raw($body, function ($message) use ($email, $subject) {
                    $message->to($email)
                            ->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning('Failed to send security alert email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendToWebhook(array $alert): void
    {
        $webhookUrl = config('tenant.security.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $client->post($webhookUrl, [
                'json' => $alert,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Alert-Signature' => $this->generateAlertSignature($alert),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send security alert to webhook', [
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getAlertRecipients(array $alert): array
    {
        $recipients = [];

        $systemAdmins = config('tenant.security.alert_emails', []);
        $recipients = array_merge($recipients, $systemAdmins);

        if ($alert['tenant_id']) {
            try {
                $tenantAdmins = User::where('tenant_id', $alert['tenant_id'])
                    ->whereHas('roles', fn($q) => $q->whereIn('slug', ['admin', 'super-admin']))
                    ->pluck('email')
                    ->toArray();
                $recipients = array_merge($recipients, $tenantAdmins);
            } catch (\Throwable $e) {
                Log::warning('Could not fetch tenant admin emails for security alert', [
                    'tenant_id' => $alert['tenant_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return array_unique($recipients);
    }

    protected function formatEmailBody(array $alert): string
    {
        $body = "SECURITY ALERT\n";
        $body .= "==============\n\n";
        $body .= "Type: {$alert['type']}\n";
        $body .= "Severity: {$alert['severity']}\n";
        $body .= "Tenant ID: {$alert['tenant_id']}\n";
        $body .= "Time: {$alert['created_at']}\n\n";
        $body .= "Details:\n";
        $body .= json_encode($alert['data'], JSON_PRETTY_PRINT) . "\n\n";
        $body .= "This alert was generated by the ACS Security System.\n";
        $body .= "Please investigate immediately if severity is CRITICAL or HIGH.\n";

        return $body;
    }

    protected function generateAlertSignature(array $alert): string
    {
        $secret = config('tenant.security.webhook_secret', config('app.key'));
        $payload = json_encode($alert);
        
        return hash_hmac('sha256', $payload, $secret);
    }

    protected function trackAlertFrequency(string $type, string $severity): void
    {
        $cacheKey = "security_alert_count:{$type}:" . now()->format('Y-m-d-H');
        $count = Cache::increment($cacheKey);
        
        if ($count === 1) {
            Cache::put($cacheKey, 1, now()->addHours(2));
        }

        if ($count > 100 && $severity !== 'critical') {
            Log::warning('High frequency of security alerts detected', [
                'type' => $type,
                'count_this_hour' => $count,
            ]);
        }
    }

    public function acknowledgeAlert(string $alertId, int $userId): bool
    {
        try {
            $updated = \DB::table('security_alerts')
                ->where('id', $alertId)
                ->update([
                    'is_acknowledged' => true,
                    'acknowledged_by' => $userId,
                    'acknowledged_at' => now(),
                    'updated_at' => now(),
                ]);

            return $updated > 0;
        } catch (\Throwable $e) {
            Log::error('Failed to acknowledge security alert', [
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getUnacknowledgedAlerts(?int $tenantId = null, int $limit = 50): array
    {
        try {
            $query = \DB::table('security_alerts')
                ->where('is_acknowledged', false)
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            return $query->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
