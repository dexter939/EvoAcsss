<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Models\DeviceCapability;
use App\Models\ProvisioningTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeviceModelingTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_parameters_creates_capabilities(): void
    {
        $device = CpeDevice::factory()->create([
            'protocol_type' => 'tr069',
            'mtp_type' => null,
            'status' => 'online'
        ]);

        // FakeParameterDiscoveryService is already registered in TestCase::setUp()
        
        $response = $this->apiPost("/api/v1/devices/{$device->id}/discover-parameters", [
            'parameter_path' => null,
            'next_level_only' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'task',
                'device'
            ]);
    }

    public function test_get_capabilities_returns_parameters(): void
    {
        $device = CpeDevice::factory()->create([
            'protocol_type' => 'tr069',
            'mtp_type' => null
        ]);
        
        DeviceCapability::create([
            'cpe_device_id' => $device->id,
            'parameter_path' => 'Device.DeviceInfo.',
            'parameter_type' => 'object',
            'writable' => false,
            'value_type' => null
        ]);

        $response = $this->apiGet("/api/v1/devices/{$device->id}/capabilities");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'format',
                'device_id',
                'capabilities'
            ]);
    }

    public function test_get_capabilities_with_filtering(): void
    {
        $device = CpeDevice::factory()->create([
            'protocol_type' => 'tr069',
            'mtp_type' => null,
            'status' => 'online'
        ]);
        
        DeviceCapability::create([
            'cpe_device_id' => $device->id,
            'parameter_path' => 'Device.WiFi.SSID.1.Enable',
            'parameter_type' => 'parameter',
            'writable' => true,
            'value_type' => 'boolean'
        ]);

        $response = $this->apiGet("/api/v1/devices/{$device->id}/capabilities?root_path=Device.WiFi.");

        $response->assertStatus(200);
        $this->assertArrayHasKey('capabilities', $response->json());
    }

    public function test_get_stats_returns_summary(): void
    {
        $device = CpeDevice::factory()->create([
            'protocol_type' => 'tr069',
            'mtp_type' => null
        ]);
        
        DeviceCapability::create([
            'cpe_device_id' => $device->id,
            'parameter_path' => 'Device.DeviceInfo.Manufacturer',
            'parameter_type' => 'parameter',
            'writable' => false,
            'value_type' => 'string'
        ]);

        $mockStats = [
            'total_capabilities' => 1,
            'by_type' => [
                'parameter' => 1,
                'object' => 0
            ],
            'writable_count' => 0,
            'readonly_count' => 1
        ];

        $mock = Mockery::mock(ParameterDiscoveryService::class);
        $mock->shouldReceive('getDiscoveryStats')
            ->once()
            ->with(Mockery::on(fn($d) => $d->id === $device->id))
            ->andReturn($mockStats);

        $this->app->instance(ParameterDiscoveryService::class, $mock);

        $response = $this->apiGet("/api/v1/devices/{$device->id}/capabilities/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'device_id',
                'serial_number',
                'stats' => [
                    'total_capabilities',
                    'by_type',
                    'writable_count',
                    'readonly_count'
                ]
            ]);
    }

    public function test_get_capability_by_path(): void
    {
        $device = CpeDevice::factory()->create([
            'protocol_type' => 'tr069',
            'mtp_type' => null,
            'status' => 'online'
        ]);
        
        DeviceCapability::create([
            'cpe_device_id' => $device->id,
            'parameter_path' => 'Device.DeviceInfo.ModelName',
            'parameter_type' => 'parameter',
            'writable' => false,
            'value_type' => 'string'
        ]);

        $response = $this->apiGet("/api/v1/devices/{$device->id}/capabilities/path?path=Device.DeviceInfo.ModelName");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'capability'
            ]);
    }
}
