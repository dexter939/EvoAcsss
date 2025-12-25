<?php

namespace Tests\Feature\MultiTenant;

use App\Events\AlarmCreated;
use App\Models\Alarm;
use App\Models\CpeDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebSocketChannelsTest extends TestCase
{

    private Tenant $tenant;
    private User $user;
    private CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->device = CpeDevice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'serial_number' => 'TEST-DEVICE-001',
        ]);
    }

    public function test_alarm_created_event_broadcasts_to_tenant_channel()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'title' => 'Test Alarm',
        ]);
        
        $event = new AlarmCreated($alarm);
        $channels = $event->broadcastOn();
        
        $channelNames = array_map(fn($c) => $c->name, $channels);
        
        $this->assertContains('private-tenant.' . $this->tenant->id, $channelNames);
    }

    public function test_alarm_created_event_broadcasts_to_severity_channel()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
            'severity' => 'critical',
            'title' => 'Critical Alarm',
        ]);
        
        $event = new AlarmCreated($alarm);
        $channels = $event->broadcastOn();
        
        $channelNames = array_map(fn($c) => $c->name, $channels);
        
        $expectedChannel = 'private-tenant.' . $this->tenant->id . '.alarms.critical';
        $this->assertContains($expectedChannel, $channelNames);
    }

    public function test_alarm_created_event_includes_tenant_id_in_payload()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
            'severity' => 'major',
            'title' => 'Test Alarm',
        ]);
        
        $event = new AlarmCreated($alarm);
        $payload = $event->broadcastWith();
        
        $this->assertArrayHasKey('tenant_id', $payload);
        $this->assertEquals($this->tenant->id, $payload['tenant_id']);
    }

    public function test_alarm_without_tenant_still_broadcasts_to_user_channels()
    {
        $this->device->users()->attach($this->user->id, ['access_level' => 'admin']);
        
        $alarm = Alarm::factory()->create([
            'tenant_id' => null,
            'device_id' => $this->device->id,
            'severity' => 'minor',
            'title' => 'Legacy Alarm',
        ]);
        
        $event = new AlarmCreated($alarm);
        $channels = $event->broadcastOn();
        
        $channelNames = array_map(fn($c) => $c->name, $channels);
        
        $this->assertContains('private-user.' . $this->user->id, $channelNames);
    }

    public function test_alarm_broadcast_name_is_correct()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
        ]);
        
        $event = new AlarmCreated($alarm);
        
        $this->assertEquals('alarm.created', $event->broadcastAs());
    }

    public function test_alarm_payload_includes_device_serial()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
        ]);
        
        $event = new AlarmCreated($alarm);
        $payload = $event->broadcastWith();
        
        $this->assertArrayHasKey('device_serial', $payload);
        $this->assertEquals('TEST-DEVICE-001', $payload['device_serial']);
    }

    public function test_alarm_payload_includes_backward_compatible_message()
    {
        $alarm = Alarm::factory()->create([
            'tenant_id' => $this->tenant->id,
            'device_id' => $this->device->id,
            'title' => 'Alarm Title',
            'description' => 'Alarm Description',
        ]);
        
        $event = new AlarmCreated($alarm);
        $payload = $event->broadcastWith();
        
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('description', $payload);
    }
}
