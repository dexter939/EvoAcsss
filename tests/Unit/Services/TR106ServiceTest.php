<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TR106Service;
use App\Models\CpeDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TR106ServiceTest extends TestCase
{
    use RefreshDatabase;

    private TR106Service $service;
    private CpeDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TR106Service::class);
        
        $this->device = CpeDevice::factory()->create([
            'serial_number' => 'TEST-TR106-001',
            'protocol_type' => 'tr069',
        ]);
    }

    public function test_get_template_definition_returns_valid_structure(): void
    {
        $result = $this->service->getTemplateDefinition('Device:2.15');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('release_date', $result);
        $this->assertArrayHasKey('total_components', $result);
        $this->assertEquals('Device:2.15', $result['version']);
    }

    public function test_get_template_definition_supports_multiple_versions(): void
    {
        $versions = ['Device:2.15', 'Device:2.14', 'Device:2.13', 'InternetGatewayDevice:1.14'];

        foreach ($versions as $version) {
            $result = $this->service->getTemplateDefinition($version);
            $this->assertEquals($version, $result['version']);
        }
    }

    public function test_get_template_definition_throws_exception_for_invalid_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported data model version');

        $this->service->getTemplateDefinition('Device:99.99');
    }

    public function test_get_parameter_inheritance_returns_chain(): void
    {
        $result = $this->service->getParameterInheritance('Device.WiFi.Radio.1.Channel');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        $paths = array_column($result, 'path');
        $this->assertContains('Device', $paths);
        $this->assertContains('Device.WiFi', $paths);
        $this->assertContains('Device.WiFi.Radio', $paths);
    }

    public function test_get_parameter_inheritance_calculates_levels(): void
    {
        $result = $this->service->getParameterInheritance('Device.WiFi.Radio.1.Channel');

        foreach ($result as $item) {
            $this->assertArrayHasKey('path', $item);
            $this->assertArrayHasKey('level', $item);
            $this->assertArrayHasKey('is_object', $item);
        }
    }

    public function test_get_default_value_for_string_type(): void
    {
        $result = $this->service->getDefaultValue('string');
        $this->assertEquals('', $result);

        $resultWithDefault = $this->service->getDefaultValue('string', ['default' => 'test']);
        $this->assertEquals('test', $resultWithDefault);
    }

    public function test_get_default_value_for_numeric_types(): void
    {
        $this->assertEquals(0, $this->service->getDefaultValue('int'));
        $this->assertEquals(0, $this->service->getDefaultValue('unsignedInt'));
        $this->assertEquals(0, $this->service->getDefaultValue('long'));
        $this->assertEquals(0, $this->service->getDefaultValue('unsignedLong'));
    }

    public function test_get_default_value_for_boolean_type(): void
    {
        $result = $this->service->getDefaultValue('boolean');
        $this->assertFalse($result);
    }

    public function test_get_default_value_for_ip_address(): void
    {
        $result = $this->service->getDefaultValue('IPAddress');
        $this->assertEquals('0.0.0.0', $result);
    }

    public function test_validate_parameter_value_accepts_valid_string(): void
    {
        $result = $this->service->validateParameterValue('string', 'test value');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_parameter_value_rejects_string_exceeding_max_length(): void
    {
        $longString = str_repeat('a', 100);
        $result = $this->service->validateParameterValue('string', $longString, ['maxLength' => 50]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_parameter_value_accepts_valid_integer(): void
    {
        $result = $this->service->validateParameterValue('int', 42);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_parameter_value_rejects_integer_out_of_range(): void
    {
        $result = $this->service->validateParameterValue('int', 150, ['min' => 1, 'max' => 100]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds maximum', $result['errors'][0]);
    }

    public function test_validate_parameter_value_accepts_valid_boolean(): void
    {
        $this->assertTrue($this->service->validateParameterValue('boolean', 'true')['valid']);
        $this->assertTrue($this->service->validateParameterValue('boolean', 'false')['valid']);
        $this->assertTrue($this->service->validateParameterValue('boolean', true)['valid']);
        $this->assertTrue($this->service->validateParameterValue('boolean', false)['valid']);
    }

    public function test_validate_parameter_value_accepts_valid_ip_address(): void
    {
        $result = $this->service->validateParameterValue('IPAddress', '192.168.1.1');
        $this->assertTrue($result['valid']);
    }

    public function test_validate_parameter_value_rejects_invalid_ip_address(): void
    {
        $result = $this->service->validateParameterValue('IPAddress', '999.999.999.999');
        $this->assertFalse($result['valid']);
    }

    public function test_validate_parameter_value_accepts_valid_mac_address(): void
    {
        $result = $this->service->validateParameterValue('MACAddress', '00:11:22:33:44:55');
        $this->assertTrue($result['valid']);
    }

    public function test_validate_parameter_value_supports_enumeration_constraint(): void
    {
        $result = $this->service->validateParameterValue(
            'string',
            'option1',
            ['enumeration' => ['option1', 'option2', 'option3']]
        );

        $this->assertTrue($result['valid']);

        $invalidResult = $this->service->validateParameterValue(
            'string',
            'option99',
            ['enumeration' => ['option1', 'option2', 'option3']]
        );

        $this->assertFalse($invalidResult['valid']);
    }

    public function test_import_data_model_xml_parses_valid_xml(): void
    {
        $xml = '<?xml version="1.0"?>
<dataModel name="TestModel" version="1.0" vendor="TestVendor">
    <parameter name="TestParam" type="string" access="readWrite">
        <description>Test parameter</description>
    </parameter>
    <object name="TestObject" access="readOnly" minEntries="0" maxEntries="1">
        <description>Test object</description>
    </object>
</dataModel>';

        $result = $this->service->importDataModelXml($xml);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data_model', $result);
        $this->assertGreaterThan(0, $result['parameters_imported']);
    }

    public function test_import_data_model_xml_handles_invalid_xml(): void
    {
        $xml = 'Invalid XML content';

        $result = $this->service->importDataModelXml($xml);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_export_data_model_xml_generates_valid_xml(): void
    {
        $dataModel = [
            'name' => 'TestModel',
            'version' => '1.0',
            'vendor' => 'TestVendor',
            'parameters' => [
                [
                    'name' => 'TestParam',
                    'type' => 'string',
                    'access' => 'readWrite',
                    'description' => 'Test description',
                ],
            ],
        ];

        $xml = $this->service->exportDataModelXml($dataModel);

        $this->assertStringContainsString('<?xml version', $xml);
        $this->assertStringContainsString('TestModel', $xml);
        $this->assertStringContainsString('TestParam', $xml);
    }

    public function test_get_parameter_constraints_merges_base_and_custom(): void
    {
        $result = $this->service->getParameterConstraints('int', ['min' => 10, 'max' => 100]);

        $this->assertIsArray($result);
        $this->assertEquals(10, $result['min']);
        $this->assertEquals(100, $result['max']);
    }

    public function test_check_version_compatibility_accepts_compatible_versions(): void
    {
        $result = $this->service->checkVersionCompatibility('Device:2.15', 'Device:2.14');

        $this->assertTrue($result['compatible']);
    }

    public function test_check_version_compatibility_rejects_incompatible_versions(): void
    {
        $result = $this->service->checkVersionCompatibility('Device:2.13', 'Device:2.15');

        $this->assertFalse($result['compatible']);
        $this->assertStringContainsString('too old', $result['reason']);
    }

    public function test_check_version_compatibility_rejects_different_roots(): void
    {
        $result = $this->service->checkVersionCompatibility('Device:2.15', 'InternetGatewayDevice:1.14');

        $this->assertFalse($result['compatible']);
        $this->assertStringContainsString('Different data model root', $result['reason']);
    }

    public function test_generate_vendor_extension_creates_valid_name(): void
    {
        $result = $this->service->generateVendorExtension('00D09E', 'CustomParam');

        $this->assertEquals('X_00D09E_CustomParam', $result);
    }

    public function test_is_valid_vendor_extension_accepts_valid_extensions(): void
    {
        $this->assertTrue($this->service->isValidVendorExtension('X_00D09E_CustomParam'));
        $this->assertTrue($this->service->isValidVendorExtension('X_ABCDEF_AnotherParam'));
    }

    public function test_is_valid_vendor_extension_rejects_invalid_extensions(): void
    {
        $this->assertFalse($this->service->isValidVendorExtension('CustomParam'));
        $this->assertFalse($this->service->isValidVendorExtension('X_InvalidOUI_Param'));
    }

    public function test_get_data_type_info_returns_type_details(): void
    {
        $result = $this->service->getDataTypeInfo('string');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('max_length', $result);
    }

    public function test_get_data_type_info_returns_null_for_invalid_type(): void
    {
        $result = $this->service->getDataTypeInfo('invalidType');

        $this->assertNull($result);
    }

    public function test_get_supported_data_types_returns_all_types(): void
    {
        $result = $this->service->getSupportedDataTypes();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        $types = array_column($result, 'type');
        $this->assertContains('string', $types);
        $this->assertContains('int', $types);
        $this->assertContains('boolean', $types);
    }
}
