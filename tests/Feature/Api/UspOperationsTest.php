<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Models\UspSubscription;
use App\Services\UspMqttService;
use App\Services\UspWebSocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class UspOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_parameters_from_usp_device(): void
    {
        // FakeUspMqttService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => 'mqtt-test-12345',
            'usp_endpoint_id' => 'proto::device-12345'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/get-params", [
            'param_paths' => [
                'Device.DeviceInfo.',
                'Device.WiFi.SSID.1.'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'status',
                    'transport'
                ]
            ]);
    }

    public function test_get_parameters_validates_tr369_device(): void
    {
        $device = CpeDevice::factory()->tr069()->create();

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Device must support TR-369 USP protocol'
            ]);
    }

    public function test_set_parameters_on_usp_device(): void
    {
        // FakeUspWebSocketService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => 'ws-client-123'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/set-params", [
            'param_paths' => [
                'Device.ManagementServer.' => [
                    'PeriodicInformInterval' => '600',
                    'PeriodicInformEnable' => 'true'
                ]
            ],
            'allow_partial' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'status'
                ]
            ]);
    }

    public function test_add_object_to_usp_device(): void
    {
        // FakeUspMqttService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/add-object", [
            'object_path' => 'Device.WiFi.SSID.',
            'parameters' => [
                'SSID' => 'NewNetwork',
                'Enable' => 'true',
                'SSIDAdvertisementEnabled' => 'true'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'object_path'
                ]
            ]);
    }

    public function test_delete_object_from_usp_device(): void
    {
        // FakeUspMqttService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/delete-object", [
            'object_paths' => [
                'Device.WiFi.SSID.3.',
                'Device.WiFi.SSID.4.'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'deleted_objects'
                ]
            ]);
    }

    public function test_operate_command_on_usp_device(): void
    {
        // FakeUspMqttService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => 'mqtt-operate-test'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/operate", [
            'command' => 'Device.Reboot()',
            'command_args' => []
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'command',
                    'status'
                ]
            ]);
    }

    public function test_reboot_usp_device(): void
    {
        // FakeUspWebSocketService is already registered in TestCase::setUp()
        
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => 'ws-reboot-test'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/reboot");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'status'
                ]
            ]);
    }

    public function test_create_subscription_on_usp_device(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create();

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/subscribe", [
            'subscription_id' => 'sub-test-001',
            'notification_type' => 'ValueChange',
            'reference_list' => [
                'Device.WiFi.SSID.1.SSID',
                'Device.DeviceInfo.UpTime'
            ],
            'enabled' => true,
            'persistent' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'subscription_id',
                    'msg_id',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('usp_subscriptions', [
            'cpe_device_id' => $device->id,
            'subscription_id' => 'sub-test-001',
            'notification_type' => 'ValueChange'
        ]);
    }

    public function test_list_device_subscriptions(): void
    {
        $device = CpeDevice::factory()->tr369()->create();
        
        UspSubscription::factory()->count(3)->create([
            'cpe_device_id' => $device->id
        ]);

        $response = $this->apiGet("/api/v1/usp/devices/{$device->id}/subscriptions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'subscription_id',
                        'notification_type',
                        'reference_list',
                        'enabled',
                        'created_at'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_delete_subscription_from_device(): void
    {
        $device = CpeDevice::factory()->tr369()->create();
        
        $subscription = UspSubscription::factory()->create([
            'cpe_device_id' => $device->id,
            'subscription_id' => 'sub-to-delete'
        ]);

        $response = $this->apiDelete("/api/v1/usp/devices/{$device->id}/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'subscription_id'
                ]
            ]);

        $this->assertSoftDeleted('usp_subscriptions', [
            'id' => $subscription->id
        ]);
    }

    public function test_usp_operations_require_online_device(): void
    {
        $device = CpeDevice::factory()->tr369()->offline()->create();

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Device must be online'
            ]);
    }

    public function test_subscription_validates_notification_type(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create();

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/subscribe", [
            'subscription_id' => 'test',
            'notification_type' => 'InvalidType',
            'reference_list' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notification_type']);
    }

    public function test_usp_operations_support_different_mtp_transports(): void
    {
        // FakeUspMqttService and FakeUspWebSocketService are already registered in TestCase::setUp()
        
        $mqttDevice = CpeDevice::factory()->tr369()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => 'mqtt-123',
            'status' => 'online'
        ]);

        $wsDevice = CpeDevice::factory()->tr369()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => 'ws-123',
            'status' => 'online'
        ]);

        $httpDevice = CpeDevice::factory()->tr369()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:7547/usp',
            'status' => 'online'
        ]);

        // Mock HTTP transport
        \Illuminate\Support\Facades\Http::fake([
            'device.test:7547/usp' => \Illuminate\Support\Facades\Http::response('', 200)
        ]);

        // Test MQTT transport
        $mqttResponse = $this->apiPost("/api/v1/usp/devices/{$mqttDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $mqttResponse->assertStatus(200)
            ->assertJsonFragment(['transport' => 'mqtt']);

        // Test WebSocket transport
        $wsResponse = $this->apiPost("/api/v1/usp/devices/{$wsDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $wsResponse->assertStatus(200)
            ->assertJsonFragment(['transport' => 'websocket']);

        // Test HTTP transport
        $httpResponse = $this->apiPost("/api/v1/usp/devices/{$httpDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $httpResponse->assertStatus(200)
            ->assertJsonFragment(['transport' => 'http']);
    }
}
