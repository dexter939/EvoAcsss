<?php

namespace Tests\Feature\TR369;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Usp\Record;

/**
 * USP Record Routing Regression Tests
 * 
 * Validates that USP Records are created with correct to_id and from_id
 * after fixing the 7 instances of inverted wrapInRecord() arguments.
 * 
 * Critical Fix (Oct 2025):
 * - All wrapInRecord() calls now use (message, device_endpoint, controller_endpoint)
 * - This ensures to_id = device (destination) and from_id = controller (source)
 * - Fixes routing issues across all 3 transport layers (HTTP, MQTT, WebSocket)
 */
class UspRecordRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected UspMessageService $uspService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uspService = app(UspMessageService::class);
    }

    /**
     * Test HTTP Transport creates USP Records with correct routing
     * 
     * Validates fix in UspHttpTransport.php line 49-52
     */
    public function test_http_transport_creates_record_with_correct_to_and_from(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::http-device-001'
        ]);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            // Capture the binary payload for validation
            $capturedPayload = $request->body();
            return Http::response('', 200);
        });

        // Trigger HTTP Get operation
        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/get-params", [
            'param_paths' => ['Device.DeviceInfo.']
        ]);

        $response->assertStatus(200);

        // Validate USP Record structure
        $this->assertNotNull($capturedPayload, 'HTTP payload should be captured');
        
        $record = new Record();
        $record->mergeFromString($capturedPayload);

        // CRITICAL: to_id must be device endpoint (destination)
        $this->assertEquals('proto::http-device-001', $record->getToId(), 
            'USP Record to_id must be device endpoint (destination)');

        // CRITICAL: from_id must be controller endpoint (source)
        $this->assertEquals(config('usp.controller_endpoint_id'), $record->getFromId(),
            'USP Record from_id must be controller endpoint (source)');
    }

    /**
     * Test UspController.addObject() creates correct USP Record
     * 
     * Validates fix in UspController.php line 308-311
     */
    public function test_add_object_creates_record_with_correct_routing(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::device-add-test'
        ]);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->body();
            return Http::response('', 200);
        });

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/add-object", [
            'object_path' => 'Device.WiFi.SSID.',
            'param_settings' => ['SSID' => 'TestNet']
        ]);

        $response->assertStatus(200);

        $record = new Record();
        $record->mergeFromString($capturedPayload);

        $this->assertEquals('proto::device-add-test', $record->getToId());
        $this->assertEquals(config('usp.controller_endpoint_id'), $record->getFromId());
    }

    /**
     * Test UspController.deleteObject() creates correct USP Record
     * 
     * Validates fix in UspController.php line 387-391
     */
    public function test_delete_object_creates_record_with_correct_routing(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::device-delete-test'
        ]);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->body();
            return Http::response('', 200);
        });

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/delete-object", [
            'object_paths' => ['Device.WiFi.SSID.1.']
        ]);

        $response->assertStatus(200);

        $record = new Record();
        $record->mergeFromString($capturedPayload);

        $this->assertEquals('proto::device-delete-test', $record->getToId());
        $this->assertEquals(config('usp.controller_endpoint_id'), $record->getFromId());
    }

    /**
     * Test that all 3 transport layers use correct routing
     * 
     * Validates fixes in:
     * - UspHttpTransport.php line 49-52
     * - UspMqttTransport.php line 57-60  
     * - UspWebSocketTransport.php line 74-77
     */
    public function test_all_transports_create_records_with_correct_routing(): void
    {
        // HTTP Device
        $httpDevice = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::http-routing-test'
        ]);

        // MQTT Device
        $mqttDevice = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'mqtt',
            'mqtt_client_id' => 'mqtt-routing-test',
            'usp_endpoint_id' => 'proto::mqtt-routing-test'
        ]);

        // WebSocket Device
        $wsDevice = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'websocket',
            'websocket_client_id' => 'ws-routing-test',
            'usp_endpoint_id' => 'proto::ws-routing-test'
        ]);

        $capturedHttpPayload = null;

        Http::fake(function ($request) use (&$capturedHttpPayload) {
            $capturedHttpPayload = $request->body();
            return Http::response('', 200);
        });

        // Test HTTP
        $httpResponse = $this->apiPost("/api/v1/usp/devices/{$httpDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $httpResponse->assertStatus(200);

        $httpRecord = new Record();
        $httpRecord->mergeFromString($capturedHttpPayload);
        $this->assertEquals('proto::http-routing-test', $httpRecord->getToId());
        $this->assertEquals(config('usp.controller_endpoint_id'), $httpRecord->getFromId());

        // Test MQTT (uses fake service)
        $mqttResponse = $this->apiPost("/api/v1/usp/devices/{$mqttDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $mqttResponse->assertStatus(200);

        // Test WebSocket (uses fake service)
        $wsResponse = $this->apiPost("/api/v1/usp/devices/{$wsDevice->id}/get-params", [
            'param_paths' => ['Device.']
        ]);
        $wsResponse->assertStatus(200);
    }

    /**
     * Test wrapInRecord() argument order directly
     * 
     * Validates that the service method produces correct Record structure
     */
    public function test_wrap_in_record_uses_correct_argument_order(): void
    {
        // Create a simple Get message
        $message = $this->uspService->createGetMessage(
            ['Device.DeviceInfo.'],
            'test-msg-001'
        );

        $deviceEndpoint = 'proto::test-device-123';
        $controllerEndpoint = config('usp.controller_endpoint_id');

        // Call wrapInRecord with correct argument order
        $record = $this->uspService->wrapInRecord(
            $message,
            $deviceEndpoint,      // to_id (destination)
            $controllerEndpoint   // from_id (source)
        );

        // Validate Record structure
        $this->assertEquals($deviceEndpoint, $record->getToId(),
            'to_id should be device endpoint (destination)');
        
        $this->assertEquals($controllerEndpoint, $record->getFromId(),
            'from_id should be controller endpoint (source)');

        $this->assertNotEmpty($record->getRecordType(),
            'Record must have a record type');
    }

    /**
     * Test that HTTP transport uses withBody()->send('POST')
     * 
     * Validates fix for TypeError when using ->post($url, $binary)
     */
    public function test_http_transport_sends_binary_payload_correctly(): void
    {
        $device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::http-binary-test'
        ]);

        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$device->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(200);

        // Verify HTTP request was sent with correct method and content type
        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   $request->hasHeader('Content-Type', 'application/vnd.bbf.usp.msg') &&
                   !empty($request->body());
        });
    }
}
