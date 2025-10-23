<?php

namespace Tests\Feature\TR369;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Services\UspMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class UspHttpTransportTest extends TestCase
{
    use RefreshDatabase;

    protected CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => 'http://device.test:8080/usp',
            'usp_endpoint_id' => 'proto::http-device-001'
        ]);
    }

    public function test_get_parameters_via_http_transport(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => [
                'Device.DeviceInfo.',
                'Device.LocalAgent.'
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
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://device.test:8080/usp' &&
                   $request->method() === 'POST';
        });
    }

    public function test_set_parameters_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/set-params", [
            'param_paths' => [
                'Device.LocalAgent.' => [
                    'PeriodicNotifInterval' => '300'
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://device.test:8080/usp' &&
                   $request->method() === 'POST';
        });
    }

    public function test_http_content_type_is_protobuf(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/vnd.bbf.usp.msg');
        });
    }

    public function test_add_object_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/add-object", [
            'object_path' => 'Device.WiFi.SSID.',
            'param_settings' => [
                'SSID' => 'TestNetwork',
                'Enable' => 'true'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'device.test:8080/usp');
        });
    }

    public function test_delete_object_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/delete-object", [
            'object_paths' => ['Device.WiFi.SSID.1.']
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'device.test:8080/usp');
        });
    }

    public function test_operate_command_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/operate", [
            'command' => 'Device.Reboot()',
            'command_key' => 'reboot-test-001'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'device.test:8080/usp');
        });
    }

    public function test_reboot_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/reboot");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'transport' => 'http'
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'device.test:8080/usp');
        });
    }

    public function test_http_requires_connection_url(): void
    {
        $deviceWithoutUrl = CpeDevice::factory()->tr369()->online()->create([
            'mtp_type' => 'http',
            'connection_request_url' => null,
            'usp_endpoint_id' => 'proto::http-device-002'
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$deviceWithoutUrl->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'HTTP connection URL not configured'
            ]);
    }

    public function test_http_handles_device_errors(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 500)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/get-params", [
            'param_paths' => ['Device.']
        ]);

        $response->assertStatus(500);
    }

    public function test_create_subscription_via_http(): void
    {
        Http::fake([
            'device.test:8080/usp' => Http::response('', 200)
        ]);

        $response = $this->apiPost("/api/v1/usp/devices/{$this->device->id}/subscribe", [
            'subscription_id' => 'boot-event-001',
            'notification_type' => 'Event',
            'reference_list' => ['Device.Boot!'],
            'persistent' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'msg_id',
                    'status',
                    'subscription_id'
                ]
            ]);
    }

}
