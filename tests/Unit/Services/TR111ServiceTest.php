<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TR111Service;
use App\Models\CpeDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TR111ServiceTest extends TestCase
{
    use RefreshDatabase;

    private TR111Service $service;
    private CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TR111Service::class);
        
        $this->device = CpeDevice::factory()->create([
            'serial_number' => 'TEST-TR111-001',
            'protocol_type' => 'tr069',
        ]);
    }

    public function test_discover_nearby_devices_finds_devices(): void
    {
        $result = $this->service->discoverNearbyDevices($this->device, ['UPnP', 'LLDP']);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('devices_found', $result);
        $this->assertArrayHasKey('discovered_devices', $result);
        $this->assertIsArray($result['discovered_devices']);
    }

    public function test_discover_nearby_devices_uses_upnp_protocol(): void
    {
        $result = $this->service->discoverNearbyDevices($this->device, ['UPnP']);

        $this->assertEquals('success', $result['status']);
        $this->assertContains('UPnP', $result['protocols_used']);
        $this->assertGreaterThan(0, $result['devices_found']);
    }

    public function test_discover_nearby_devices_uses_lldp_protocol(): void
    {
        $result = $this->service->discoverNearbyDevices($this->device, ['LLDP']);

        $this->assertEquals('success', $result['status']);
        $this->assertContains('LLDP', $result['protocols_used']);
    }

    public function test_analyze_proximity_with_immediate_range(): void
    {
        $result = $this->service->analyzeProximity(-35);

        $this->assertEquals('immediate', $result['proximity_level']);
        $this->assertEquals(-35, $result['rssi_dbm']);
        $this->assertArrayHasKey('estimated_distance_m', $result);
    }

    public function test_analyze_proximity_with_near_range(): void
    {
        $result = $this->service->analyzeProximity(-55);

        $this->assertEquals('near', $result['proximity_level']);
    }

    public function test_analyze_proximity_with_out_of_range(): void
    {
        $result = $this->service->analyzeProximity(-110);

        $this->assertEquals('out_of_range', $result['proximity_level']);
        $this->assertNull($result['estimated_distance_m']);
    }

    public function test_build_topology_map_creates_structure(): void
    {
        $result = $this->service->buildTopologyMap($this->device);

        $this->assertArrayHasKey('root_device', $result);
        $this->assertArrayHasKey('discovered_devices', $result);
        $this->assertArrayHasKey('relationships', $result);
        $this->assertArrayHasKey('total_devices', $result);
    }

    public function test_track_relationship_creates_relationship(): void
    {
        $result = $this->service->trackRelationship('device1', 'device2', 'child');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('device1', $result['device_1']);
        $this->assertEquals('device2', $result['device_2']);
        $this->assertEquals('child', $result['relationship']);
    }

    public function test_track_relationship_rejects_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid relationship type');

        $this->service->trackRelationship('device1', 'device2', 'invalid_type');
    }

    public function test_monitor_proximity_events_detects_events(): void
    {
        $eventsReceived = [];
        
        $callback = function($event) use (&$eventsReceived) {
            $eventsReceived[] = $event;
        };

        $result = $this->service->monitorProximityEvents($this->device, $callback);

        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThan(0, $result['events_detected']);
        $this->assertNotEmpty($eventsReceived);
    }

    public function test_get_upnp_device_details_returns_structure(): void
    {
        $result = $this->service->getUpnpDeviceDetails('test_device_id');

        $this->assertArrayHasKey('device_id', $result);
        $this->assertArrayHasKey('device_type', $result);
        $this->assertArrayHasKey('friendly_name', $result);
        $this->assertArrayHasKey('services', $result);
    }

    public function test_get_lldp_neighbor_info_returns_structure(): void
    {
        $result = $this->service->getLldpNeighborInfo('00:11:22:33:44:55');

        $this->assertArrayHasKey('chassis_id', $result);
        $this->assertArrayHasKey('port_id', $result);
        $this->assertArrayHasKey('system_name', $result);
    }

    public function test_get_all_parameters_returns_proximity_parameters(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Device.ProximityDetection.Enable', $result);
        $this->assertArrayHasKey('Device.ProximityDetection.NumberOfProtocols', $result);
    }

    public function test_is_valid_parameter_accepts_proximity_parameters(): void
    {
        $this->assertTrue($this->service->isValidParameter('Device.ProximityDetection.Enable'));
        $this->assertTrue($this->service->isValidParameter('Device.ProximityDetection.NumberOfProtocols'));
    }

    public function test_is_valid_parameter_rejects_non_proximity_parameters(): void
    {
        $this->assertFalse($this->service->isValidParameter('Device.WiFi.Radio.1.Enable'));
    }
}
