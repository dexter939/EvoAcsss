<?php

namespace App\Events;

use App\Models\Alarm;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AlarmCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Alarm $alarm;

    /**
     * Create a new event instance.
     */
    public function __construct(Alarm $alarm)
    {
        $this->alarm = $alarm;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * Multi-tenant isolation: Broadcasts to both:
     * 1. Tenant-wide channel (all users in the alarm's tenant)
     * 2. User-specific channels (users with explicit device access)
     * 3. Severity-specific tenant channel (for filtered subscriptions)
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast to tenant channel if alarm has tenant_id
        if ($this->alarm->tenant_id) {
            $channels[] = new PrivateChannel('tenant.' . $this->alarm->tenant_id);
            
            // Also broadcast to severity-specific channel
            if ($this->alarm->severity) {
                $channels[] = new PrivateChannel('tenant.' . $this->alarm->tenant_id . '.alarms.' . $this->alarm->severity);
            }
            
            Log::info('Broadcasting alarm to tenant channel', [
                'alarm_id' => $this->alarm->id,
                'tenant_id' => $this->alarm->tenant_id,
                'severity' => $this->alarm->severity,
            ]);
        }
        
        // Also broadcast to user-specific channels for backward compatibility
        if ($this->alarm->device) {
            $authorizedUsers = $this->alarm->device->users()->get();
            
            foreach ($authorizedUsers as $user) {
                $channels[] = new PrivateChannel('user.' . $user->id);
            }
            
            if ($authorizedUsers->isNotEmpty()) {
                Log::info('Broadcasting alarm to user channels', [
                    'alarm_id' => $this->alarm->id,
                    'device_serial' => $this->alarm->device->serial_number,
                    'user_count' => $authorizedUsers->count(),
                ]);
            }
        }
        
        // If no channels available, log warning
        if (empty($channels)) {
            Log::warning('Alarm will not be broadcast - no tenant or device association', [
                'alarm_id' => $this->alarm->id,
                'alarm_type' => $this->alarm->alarm_type,
            ]);
        }
        
        return array_unique($channels, SORT_REGULAR);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'alarm.created';
    }

    /**
     * Get the data to broadcast.
     * 
     * BACKWARD COMPATIBILITY: Includes both legacy `message` field
     * and new `title`/`description` fields.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->alarm->id,
            'tenant_id' => $this->alarm->tenant_id,
            'device_id' => $this->alarm->device_id,
            'device_serial' => $this->alarm->device?->serial_number,
            'severity' => $this->alarm->severity,
            // Backward compatibility: keep `message` for legacy clients
            'message' => $this->alarm->title ?? $this->alarm->description,
            // New fields for detailed info
            'title' => $this->alarm->title,
            'description' => $this->alarm->description,
            'status' => $this->alarm->status,
            'category' => $this->alarm->category,
            'alarm_type' => $this->alarm->alarm_type,
            'raised_at' => $this->alarm->raised_at?->toISOString(),
            'created_at' => $this->alarm->created_at?->toISOString(),
        ];
    }
}
