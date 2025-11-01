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
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alarms'),
            new PrivateChannel('user.' . $this->alarm->user_id ?? 'system'),
        ];
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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->alarm->id,
            'device_serial' => $this->alarm->device_serial,
            'severity' => $this->alarm->severity,
            'message' => $this->alarm->message,
            'status' => $this->alarm->status,
            'created_at' => $this->alarm->created_at?->toISOString(),
        ];
    }
}
