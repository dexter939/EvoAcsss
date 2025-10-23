<?php

namespace Tests\Feature\TR369;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Services\UspMqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UspMqttTransportTest extends TestCase
{
    use RefreshDatabase;

    protected CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        // FakeUspMqttService is already registered in TestCase::setUp()

        $this->device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => 'mqtt-test-device-001',
            'usp_endpoint_id' => 'proto::mqtt-device-001'
        ]);
    }

    public function test_get_parameters_via_mqtt_transport(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
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
            ])
            ->assertJsonFragment([
                'transport' => 'mqtt'
            ]);

        $msgId = $response->json('data.msg_id');
        $this->assertNotEmpty($msgId);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]+$/', $msgId);
    }

    public function test_set_parameters_via_mqtt_with_allow_partial(): void
    {
        // FakeUspMqttService is already registered
        
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/set-params", [
            'param_paths' => [
                'Device.ManagementServer.' => [
                    'PeriodicInformInterval' => '600',
                    'PeriodicInformEnable' => 'true'
                ]
            ],
            'allow_partial' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'mqtt',
                'success' => true
            ]);

        $msgId = $response->json('data.msg_id');
        $this->assertNotEmpty($msgId);
        
        // Fake service handles the call without needing verification
    }

    public function test_mqtt_topic_routing_for_device(): void
    {
        // Use real service for testing helper methods (not API calls)
        $uspMessageService = app(\App\Services\UspMessageService::class);
        $realMqttService = new UspMqttService($uspMessageService);

        // Test request topic
        $requestTopic = $realMqttService->buildTopicForDevice($this->device);
        $this->assertEquals('usp/request/proto::mqtt-device-001', $requestTopic);

        // Test response topic
        $responseTopic = $realMqttService->buildResponseTopic($this->device);
        $this->assertEquals('usp/response/proto::mqtt-device-001', $responseTopic);

        // Test endpoint ID extraction
        $extractedId = $realMqttService->extractEndpointIdFromTopic('usp/request/proto::mqtt-device-001');
        $this->assertEquals('proto::mqtt-device-001', $extractedId);
    }

    public function test_mqtt_publish_uses_qos_1(): void
    {
        // Use real service for testing property values (not API calls)
        $uspMessageService = app(\App\Services\UspMessageService::class);
        $realMqttService = new UspMqttService($uspMessageService);

        // Verify QoS level for at-least-once delivery
        $reflection = new \ReflectionClass($realMqttService);
        $property = $reflection->getProperty('qos');
        $property->setAccessible(true);
        
        $qos = $property->getValue($realMqttService);
        $this->assertEquals(1, $qos, 'MQTT QoS should be 1 for at-least-once delivery');
    }

    public function test_add_object_via_mqtt(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/add-object", [
            'object_path' => 'Device.WiFi.SSID.',
            'parameters' => [
                'SSID' => 'GuestNetwork',
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
                'transport' => 'mqtt'
            ]);
    }

    public function test_delete_object_via_mqtt(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/delete-object", [
            'object_paths' => [
                'Device.WiFi.SSID.3.',
                'Device.WiFi.SSID.4.'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'mqtt',
                'success' => true
            ]);

        $deletedObjects = $response->json('data.deleted_objects');
        $this->assertIsArray($deletedObjects);
    }

    public function test_operate_command_via_mqtt(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/operate", [
            'command' => 'Device.WiFi.Radio.1.Stats.Reset()',
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
            ])
            ->assertJsonFragment([
                'transport' => 'mqtt'
            ]);
    }

    public function test_mqtt_subscription_creation(): void
    {
        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/subscribe", [
            'subscription_id' => 'mqtt-sub-001',
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
            'cpe_device_id' => $this->device->id,
            'subscription_id' => 'mqtt-sub-001',
            'notification_type' => 'ValueChange'
        ]);
    }

    public function test_mqtt_device_requires_mqtt_client_id(): void
    {
        $invalidDevice = CpeDevice::factory()->tr369()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => null,
            'status' => 'online'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$invalidDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'MQTT client ID not configured'
            ]);
    }

    public function test_mqtt_msgid_uniqueness(): void
    {
        $response1 = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => ['Device.DeviceInfo.']
        ]);

        $response2 = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => ['Device.WiFi.']
        ]);

        $msgId1 = $response1->json('data.msg_id');
        $msgId2 = $response2->json('data.msg_id');

        $this->assertNotEquals($msgId1, $msgId2, 'Message IDs must be unique');
    }
}
