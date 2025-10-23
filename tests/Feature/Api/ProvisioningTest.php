<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\CpeDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_parameters_from_tr069_device(): void
    {
        $device = CpeDevice::factory()->tr069()->online()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/parameters/get", [
            'parameters' => [
                'Device.DeviceInfo.SoftwareVersion',
                'Device.WiFi.SSID.1.SSID'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task_id',
                    'status',
                    'parameters'
                ]
            ]);
    }

    public function test_get_parameters_validates_input(): void
    {
        $device = CpeDevice::factory()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/parameters/get", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parameters']);
    }

    public function test_set_parameters_on_tr069_device(): void
    {
        $device = CpeDevice::factory()->tr069()->online()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/parameters/set", [
            'parameters' => [
                'Device.ManagementServer.PeriodicInformInterval' => '600',
                'Device.WiFi.SSID.1.SSID' => 'NewNetworkName'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task_id',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('provisioning_tasks', [
            'cpe_device_id' => $device->id,
            'task_type' => 'set_parameters'
        ]);
    }

    public function test_set_parameters_requires_valid_device(): void
    {
        $response = $this->apiPost('/api/v1/devices/99999/parameters/set', [
            'parameters' => ['Device.Test' => 'value']
        ]);

        $response->assertStatus(404);
    }

    public function test_reboot_device_creates_task(): void
    {
        $device = CpeDevice::factory()->online()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/reboot");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task_id',
                    'message'
                ]
            ]);

        $this->assertDatabaseHas('provisioning_tasks', [
            'cpe_device_id' => $device->id,
            'task_type' => 'reboot'
        ]);
    }

    public function test_reboot_requires_online_device(): void
    {
        $device = CpeDevice::factory()->offline()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/reboot");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Device must be online to reboot'
            ]);
    }

    public function test_connection_request_initiates_device_connection(): void
    {
        // FakeConnectionRequestService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr069()->create([
            'status' => 'online',
            'connection_request_url' => 'http://device.example.com:7547',
            'connection_request_username' => 'admin',
            'connection_request_password' => 'password'
        ]);

        $response = $this->apiPost("/api/v1/devices/{$device->id}/connection-request");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'message'
                ]
            ]);
    }

    public function test_provision_device_applies_configuration_profile(): void
    {
        // Crea configuration profile per test
        // Create configuration profile for test
        $profile = \App\Models\ConfigurationProfile::create([
            'name' => 'Test Profile',
            'description' => 'Test configuration profile',
            'parameters' => [
                'Device.WiFi.SSID.1.SSID' => 'TestNetwork',
                'Device.ManagementServer.PeriodicInformInterval' => '300'
            ],
            'is_active' => true
        ]);

        $device = CpeDevice::factory()->create();

        $response = $this->apiPost("/api/v1/devices/{$device->id}/provision", [
            'profile_id' => $profile->id,
            'parameters' => [
                'Device.WiFi.SSID.1.SSID' => 'ProvisionedNetwork'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'task_id',
                    'status'
                ]
            ]);
    }

    public function test_list_tasks_returns_provisioning_tasks(): void
    {
        $device = CpeDevice::factory()->create();
        
        // Create some tasks via API
        $this->apiPost("/api/v1/devices/{$device->id}/reboot");
        $this->apiPost("/api/v1/devices/{$device->id}/parameters/set", [
            'parameters' => ['Device.Test' => 'value']
        ]);

        $response = $this->apiGet('/api/v1/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_type',
                        'status',
                        'cpe_device_id',
                        'created_at'
                    ]
                ]
            ]);
    }

    public function test_get_task_returns_task_details(): void
    {
        $device = CpeDevice::factory()->create();
        
        $taskResponse = $this->apiPost("/api/v1/devices/{$device->id}/reboot");
        $taskId = $taskResponse->json('data.task_id');

        $response = $this->apiGet("/api/v1/tasks/{$taskId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'task_type',
                    'status',
                    'parameters',
                    'result'
                ]
            ]);
    }
}
