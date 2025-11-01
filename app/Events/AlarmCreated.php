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
     * Multi-tenant isolation: Only broadcast to users with explicit device access.
     * Alarms without device association are NOT broadcast (logged only).
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Only broadcast if alarm is associated with a device
        if (!$this->alarm->device) {
            Log::warning('Alarm without device association will not be broadcast', [
                'alarm_id' => $this->alarm->id,
                'alarm_type' => $this->alarm->alarm_type,
            ]);
            return [];
        }
        
        // Get all users with explicit access to this alarm's device
        // SECURITY: Uses user_devices pivot table for multi-tenant isolation
        $authorizedUsers = $this->alarm->device->users()->get();
        
        if ($authorizedUsers->isEmpty()) {
            Log::warning('No authorized users for device, alarm will not be broadcast', [
                'alarm_id' => $this->alarm->id,
                'device_id' => $this->alarm->device_id,
                'device_serial' => $this->alarm->device->serial_number,
            ]);
            return [];
        }
        
        // Broadcast to each authorized user's private channel
        foreach ($authorizedUsers as $user) {
            $channels[] = new PrivateChannel('user.' . $user->id);
        }
        
        Log::info('Broadcasting alarm to authorized users', [
            'alarm_id' => $this->alarm->id,
            'device_serial' => $this->alarm->device->serial_number,
            'user_count' => count($channels),
        ]);
        
        return $channels;
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
