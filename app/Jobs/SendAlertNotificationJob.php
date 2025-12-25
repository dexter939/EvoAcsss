<?php

namespace App\Jobs;

use App\Models\AlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Traits\TenantAwareJob;

class SendAlertNotificationJob implements ShouldQueue
{
    use Queueable, TenantAwareJob;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    protected $alert;
    protected $channel;
    protected $recipients;

    /**
     * Create a new job instance.
     */
    public function __construct(AlertNotification $alert, string $channel, array $recipients)
    {
        $this->alert = $alert;
        $this->channel = $channel;
        $this->recipients = $recipients;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            switch ($this->channel) {
                case 'email':
                    $this->sendEmail();
                    break;
                case 'webhook':
                    $this->sendWebhook();
                    break;
                case 'slack':
                    $this->sendSlack();
                    break;
                case 'sms':
                    $this->sendSMS();
                    break;
            }

            $this->alert->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            Log::info("Alert notification sent", [
                'alert_id' => $this->alert->id,
                'channel' => $this->channel,
            ]);
        } catch (\Exception $e) {
            $this->alert->increment('retry_count');
            $this->alert->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Failed to send alert notification", [
                'alert_id' => $this->alert->id,
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function sendEmail()
    {
        foreach ($this->recipients as $email) {
            Mail::raw($this->formatMessage(), function ($message) use ($email) {
                $message->to($email)
                    ->subject("[{$this->alert->severity}] {$this->alert->title}");
            });
        }
    }

    protected function sendWebhook()
    {
        foreach ($this->recipients as $webhookUrl) {
            Http::post($webhookUrl, [
                'alert_id' => $this->alert->id,
                'type' => $this->alert->alert_type,
                'severity' => $this->alert->severity,
                'title' => $this->alert->title,
                'message' => $this->alert->message,
                'metadata' => $this->alert->metadata,
                'timestamp' => $this->alert->created_at->toIso8601String(),
            ]);
        }
    }

    protected function sendSlack()
    {
        $color = $this->getSeverityColor();

        foreach ($this->recipients as $webhookUrl) {
            Http::post($webhookUrl, [
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => $this->alert->title,
                        'text' => $this->alert->message,
                        'fields' => [
                            [
                                'title' => 'Severity',
                                'value' => strtoupper($this->alert->severity),
                                'short' => true,
                            ],
                            [
                                'title' => 'Type',
                                'value' => ucfirst($this->alert->alert_type),
                                'short' => true,
                            ],
                        ],
                        'footer' => 'ACS Alert System',
                        'ts' => $this->alert->created_at->timestamp,
                    ],
                ],
            ]);
        }
    }

    protected function sendSMS()
    {
        Log::info("SMS notification not configured", [
            'alert_id' => $this->alert->id,
        ]);
    }

    protected function formatMessage()
    {
        return "Alert Notification\n\n"
            . "Severity: " . strtoupper($this->alert->severity) . "\n"
            . "Type: " . ucfirst($this->alert->alert_type) . "\n"
            . "Title: {$this->alert->title}\n\n"
            . "Message:\n{$this->alert->message}\n\n"
            . "Time: " . $this->alert->created_at->format('Y-m-d H:i:s') . "\n";
    }

    protected function getSeverityColor()
    {
        return match ($this->alert->severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => '#ff9800',
            'low' => 'good',
            default => '#808080',
        };
    }
}
