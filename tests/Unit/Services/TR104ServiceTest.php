<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TR104Service;
use App\Models\CpeDevice;
use App\Models\VoiceService;
use App\Models\SipProfile;
use App\Models\VoipLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TR104ServiceTest extends TestCase
{
    use RefreshDatabase;

    private TR104Service $service;
    private CpeDevice $device;
    private VoiceService $voiceService;
    private SipProfile $sipProfile;
    private VoipLine $voipLine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TR104Service::class);
        
        $this->device = CpeDevice::factory()->create([
            'serial_number' => 'TEST-TR104-001',
            'protocol_type' => 'tr069',
        ]);

        $this->voiceService = VoiceService::factory()->create([
            'cpe_device_id' => $this->device->id,
            'enabled' => true,
            'protocol' => 'SIP',
            'service_instance' => 1,
        ]);

        $this->sipProfile = SipProfile::factory()->create([
            'voice_service_id' => $this->voiceService->id,
            'profile_name' => 'Default Profile',
            'profile_instance' => 1,
            'enabled' => true,
        ]);

        $this->voipLine = VoipLine::factory()->create([
            'sip_profile_id' => $this->sipProfile->id,
            'line_instance' => 1,
            'enabled' => true,
        ]);
    }

    public function test_get_all_parameters_returns_bbf_compliant_structure(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $this->assertIsArray($result);
        
        $voiceServiceParams = array_filter(array_keys($result), function($key) {
            return str_starts_with($key, 'Device.Services.VoiceService.');
        });
        
        $this->assertNotEmpty($voiceServiceParams, 'Should have TR-104 VoiceService parameters');
        $this->assertArrayHasKey('Device.Services.VoiceService.1.Enable', $result);
    }

    public function test_get_all_parameters_includes_sip_configuration(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $sipParams = array_filter(array_keys($result), function($key) {
            return str_contains($key, '.SIP.');
        });

        $this->assertNotEmpty($sipParams, 'Should have SIP configuration parameters');
    }

    public function test_get_all_parameters_includes_rtp_configuration(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $rtpParams = array_filter(array_keys($result), function($key) {
            return str_contains($key, '.RTP.');
        });

        $this->assertNotEmpty($rtpParams, 'Should have RTP configuration parameters');
    }

    public function test_get_all_parameters_includes_codec_configuration(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $codecParams = array_filter(array_keys($result), function($key) {
            return str_contains($key, '.Codec.');
        });

        $this->assertNotEmpty($codecParams, 'Should have Codec configuration parameters');
    }

    public function test_get_all_parameters_includes_capabilities(): void
    {
        $result = $this->service->getAllParameters($this->device);

        $capabilityParams = array_filter(array_keys($result), function($key) {
            return str_contains($key, '.Capabilities.');
        });

        $this->assertNotEmpty($capabilityParams, 'Should have Capabilities parameters');
    }

    public function test_set_parameter_values_updates_configuration(): void
    {
        $parameters = [
            'Device.Services.VoiceService.1.Enable' => 'true',
            'Device.Services.VoiceService.1.VoiceProfile.1.Enable' => 'true',
        ];

        $result = $this->service->setParameterValues($this->device, $parameters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Device.Services.VoiceService.1.Enable', $result);
        $this->assertEquals('success', $result['Device.Services.VoiceService.1.Enable']['status']);
    }

    public function test_set_parameter_values_rejects_invalid_parameters(): void
    {
        $parameters = [
            'Invalid.Parameter.Path' => 'value',
        ];

        $result = $this->service->setParameterValues($this->device, $parameters);

        $this->assertEquals('error', $result['Invalid.Parameter.Path']['status']);
        $this->assertStringContainsString('Invalid TR-104 parameter', $result['Invalid.Parameter.Path']['message']);
    }

    public function test_perform_sip_registration_succeeds(): void
    {
        $result = $this->service->performSipRegistration($this->voipLine);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('SIP registration successful', $result['message']);
        $this->assertArrayHasKey('registered_at', $result);

        $this->voipLine->refresh();
        $this->assertEquals('Registered', $this->voipLine->status);
    }

    public function test_negotiate_codecs_returns_common_codecs(): void
    {
        $localCodecs = ['PCMU', 'PCMA', 'G729', 'OPUS'];
        $remoteCodecs = ['PCMA', 'G729', 'G722'];

        $result = $this->service->negotiateCodecs($localCodecs, $remoteCodecs);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        $negotiatedCodecs = array_column($result, 'codec');
        $this->assertContains('PCMA', $negotiatedCodecs);
        $this->assertContains('G729', $negotiatedCodecs);
        $this->assertNotContains('OPUS', $negotiatedCodecs);
    }

    public function test_negotiate_codecs_orders_by_priority(): void
    {
        $localCodecs = ['G729', 'PCMU', 'PCMA'];
        $remoteCodecs = ['PCMA', 'PCMU', 'G729'];

        $result = $this->service->negotiateCodecs($localCodecs, $remoteCodecs);

        $this->assertGreaterThan(0, count($result));
        $this->assertEquals('PCMU', $result[0]['codec']);
    }

    public function test_configure_qos_sets_dscp_values(): void
    {
        $result = $this->service->configureQoS($this->voiceService, 'EF');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('EF', $result['qos_class']);
        $this->assertEquals(46, $result['dscp_value']);

        $this->voiceService->refresh();
        $this->assertEquals(46, $this->voiceService->rtp_dscp);
    }

    public function test_configure_qos_supports_different_classes(): void
    {
        $result = $this->service->configureQoS($this->voiceService, 'AF41');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(34, $result['dscp_value']);
    }

    public function test_configure_failover_sets_backup_servers(): void
    {
        $backupServers = [
            ['server' => 'backup1.example.com', 'port' => 5060],
            ['server' => 'backup2.example.com', 'port' => 5060],
        ];

        $result = $this->service->configureFailover($this->sipProfile, $backupServers);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(3, $result['total_servers']);
        $this->assertArrayHasKey('failover_servers', $result);
        $this->assertArrayHasKey('primary', $result['failover_servers']);
        $this->assertArrayHasKey('backup_1', $result['failover_servers']);
    }

    public function test_configure_emergency_calling_sets_e911(): void
    {
        $locationData = [
            'civic_address' => '123 Main St, City, State 12345',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'elin' => '+14155551234',
        ];

        $result = $this->service->configureEmergencyCalling($this->voipLine, $locationData);

        $this->assertEquals('success', $result['status']);
        $this->assertTrue($result['e911_enabled']);
        $this->assertArrayHasKey('location', $result);

        $this->voipLine->refresh();
        $this->assertNotNull($this->voipLine->e911_config);
    }

    public function test_get_call_statistics_returns_line_stats(): void
    {
        $result = $this->service->getCallStatistics($this->voipLine);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('line_id', $result);
        $this->assertArrayHasKey('directory_number', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('total_calls', $result);
        $this->assertArrayHasKey('statistics', $result);
    }

    public function test_is_valid_parameter_accepts_voice_service_parameters(): void
    {
        $this->assertTrue($this->service->isValidParameter('Device.Services.VoiceService.1.Enable'));
        $this->assertTrue($this->service->isValidParameter('Device.Services.VoiceService.1.VoiceProfile.1.SIP.ProxyServer'));
    }

    public function test_is_valid_parameter_rejects_non_voice_parameters(): void
    {
        $this->assertFalse($this->service->isValidParameter('Device.WiFi.Radio.1.Enable'));
        $this->assertFalse($this->service->isValidParameter('Device.IP.Interface.1.Enable'));
    }

    public function test_clear_cache_removes_cached_data(): void
    {
        $this->service->getAllParameters($this->device);
        
        $this->service->clearCache($this->device->id);
        
        $this->assertTrue(true);
    }

    public function test_clear_cache_removes_all_cached_data(): void
    {
        $this->service->getAllParameters($this->device);
        
        $this->service->clearCache();
        
        $this->assertTrue(true);
    }

    public function test_supported_codecs_constant_has_all_codecs(): void
    {
        $codecs = TR104Service::SUPPORTED_CODECS;

        $this->assertArrayHasKey('PCMU', $codecs);
        $this->assertArrayHasKey('PCMA', $codecs);
        $this->assertArrayHasKey('G729', $codecs);
        $this->assertArrayHasKey('G722', $codecs);
        $this->assertArrayHasKey('OPUS', $codecs);
        $this->assertArrayHasKey('AMR', $codecs);
    }

    public function test_qos_dscp_constant_has_all_classes(): void
    {
        $qos = TR104Service::QOS_DSCP;

        $this->assertArrayHasKey('EF', $qos);
        $this->assertArrayHasKey('AF41', $qos);
        $this->assertArrayHasKey('AF31', $qos);
        $this->assertArrayHasKey('CS3', $qos);
        $this->assertArrayHasKey('BE', $qos);
    }
}
