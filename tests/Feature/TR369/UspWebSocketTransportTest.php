<?php

namespace Tests\Feature\TR369;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Services\UspWebSocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UspWebSocketTransportTest extends TestCase
{
    use RefreshDatabase;

    protected CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        // FakeUspWebSocketService is already registered in TestCase::setUp()

        $this->device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => 'ws-test-client-001',
            'usp_endpoint_id' => 'proto::ws-device-001'
        ]);
    }

    public function test_get_parameters_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => [
                'Device.DeviceInfo.SoftwareVersion',
                'Device.WiFi.SSID.1.SSID'
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
            ])
            ->assertJsonFragment([
                'transport' => 'websocket'
            ]);
    }

    public function test_set_parameters_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/set-params", [
            'param_paths' => [
                'Device.WiFi.SSID.1.' => [
                    'SSID' => 'WebSocketNetwork',
                    'Enable' => 'true'
                ]
            ],
            'allow_partial' => false
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'websocket',
                'success' => true
            ]);
    }

    public function test_websocket_connection_tracking(): void
    {
        $wsService = app(UspWebSocketService::class);

        // Check if device is tracked as connected (will be false in test env)
        $isConnected = $wsService->isDeviceConnected($this->device);
        $this->assertIsBool($isConnected);

        // Get connected devices list
        $connectedDevices = $wsService->getConnectedDevices();
        $this->assertIsArray($connectedDevices);
    }

    public function test_add_object_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/add-object", [
            'object_path' => 'Device.WiFi.AccessPoint.',
            'parameters' => [
                'SSIDReference' => 'Device.WiFi.SSID.1',
                'Enable' => 'true'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'object_path'
                ]
            ])
            ->assertJsonFragment([
                'transport' => 'websocket'
            ]);
    }

    public function test_delete_multiple_objects_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/delete-object", [
            'object_paths' => [
                'Device.WiFi.SSID.5.',
                'Device.WiFi.SSID.6.',
                'Device.WiFi.SSID.7.'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'websocket'
            ]);

        $deletedObjects = $response->json('data.deleted_objects');
        $this->assertCount(3, $deletedObjects);
    }

    public function test_operate_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/operate", [
            'command' => 'Device.SelfTest.DiagnosticsState()',
            'command_args' => [
                'Mode' => 'Full'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'websocket'
            ]);
    }

    public function test_reboot_via_websocket(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/reboot");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'status'
                ]
            ])
            ->assertJsonFragment([
                'transport' => 'websocket'
            ]);
    }

    public function test_websocket_subscription_with_notify(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/subscribe", [
            'subscription_id' => 'ws-notify-001',
            'notification_type' => 'Event',
            'reference_list' => [
                'Device.Boot!'
            ],
            'enabled' => true,
            'persistent' => false
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'subscription_id',
                    'msg_id'
                ]
            ]);

        $this->assertDatabaseHas('usp_subscriptions', [
            'cpe_device_id' => $this->device->id,
            'subscription_id' => 'ws-notify-001',
            'notification_type' => 'Event',
            'enabled' => true
        ]);
    }

    public function test_websocket_requires_client_id(): void
    {
        $invalidDevice = CpeDevice::factory()->tr369()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => null,
            'status' => 'online'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$invalidDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'WebSocket client ID not configured'
            ]);
    }

    public function test_websocket_msgid_correlation(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => ['Device.DeviceInfo.']
        ]);

        $msgId = $response->json('data.msg_id');

        // Verify msgId format (UUID-like)
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $msgId,
            'Message ID should be UUID format'
        );
    }

    public function test_list_websocket_device_subscriptions(): void
    {
        // Create multiple subscriptions
        $this->apiPost("/api/v1/usp/devices/{$this->device->id}/subscribe", [
            'subscription_id' => 'ws-sub-1',
            'notification_type' => 'ValueChange',
            'reference_list' => ['Device.WiFi.']
        ]);

        $this->apiPost("/api/v1/usp/devices/{$this->device->id}/subscribe", [
            'subscription_id' => 'ws-sub-2',
            'notification_type' => 'ObjectCreation',
            'reference_list' => ['Device.WiFi.SSID.']
        ]);

        $response = $this->apiGet("/api/v1/usp/devices/{$this->device->id}/subscriptions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'subscription_id',
                        'notification_type',
                        'reference_list',
                        'enabled'
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }
}
