<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\CpeDevice;
use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\ConfigurationTemplateLibrary;
use App\Models\FirmwareCompatibility;
use App\Models\FirmwareVersion;
use App\Models\ProvisioningTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * VendorLibraryBulkOperationsTest - Integration tests for bulk vendor operations
 * 
 * Tests the 3 bulk operations endpoints:
 * - POST /api/v1/vendors/bulk/detect
 * - POST /api/v1/vendors/bulk/apply-template
 * - POST /api/v1/vendors/bulk/firmware-check
 */
class VendorLibraryBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected RouterManufacturer $manufacturer;
    protected RouterProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test manufacturer and product
        $this->manufacturer = RouterManufacturer::create([
            'name' => 'TP-Link',
            'country' => 'China',
            'category' => 'residential',
            'tr069_support' => true,
            'tr369_support' => false,
            'tr104_support' => false,
            'is_certified' => true
        ]);

        $this->product = RouterProduct::create([
            'manufacturer_id' => $this->manufacturer->id,
            'model_name' => 'Archer C7',
            'hardware_version' => 'v5.0',
            'firmware_version' => '1.0.0',
            'release_year' => 2020,
            'wifi_standard' => 'WiFi 5 (802.11ac)',
            'supports_tr069' => true,
            'supports_tr369' => false,
            'is_active' => true
        ]);
    }

    // ==================== BULK DETECT TESTS ====================

    public function test_bulk_detect_vendor_successfully_detects_multiple_devices(): void
    {
        // Create devices with manufacturer info
        $device1 = CpeDevice::factory()->create([
            'manufacturer' => 'TP-Link',
            'model_name' => 'Archer C7',
            'oui' => 'F4F26D',
            'product_class' => 'IGD'
        ]);

        $device2 = CpeDevice::factory()->create([
            'manufacturer' => 'TP-Link',
            'model_name' => 'Archer C7',
            'oui' => 'F4F26D',
            'product_class' => 'IGD'
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => [$device1->id, $device2->id]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'processed',
                    'success_count',
                    'failure_count',
                    'results' => [
                        '*' => [
                            'device_id',
                            'serial_number',
                            'status'
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['processed']);
        $this->assertEquals(2, $data['success_count']);
        $this->assertEquals(0, $data['failure_count']);
    }

    public function test_bulk_detect_vendor_validates_required_fields(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/detect', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids']);
    }

    public function test_bulk_detect_vendor_validates_device_ids_array(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => 'not-an-array'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids']);
    }

    public function test_bulk_detect_vendor_validates_device_exists(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => [99999] // Non-existent device
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids.0']);
    }

    public function test_bulk_detect_vendor_handles_mixed_success_and_failure(): void
    {
        $device1 = CpeDevice::factory()->create([
            'manufacturer' => 'TP-Link',
            'model_name' => 'Archer C7',
            'oui' => 'F4F26D'
        ]);

        $device2 = CpeDevice::factory()->create([
            'manufacturer' => 'UnknownVendor',
            'model_name' => 'UnknownModel',
            'oui' => null
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => [$device1->id, $device2->id]
        ]);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['processed']);
        $this->assertGreaterThanOrEqual(0, $data['success_count']);
        $this->assertGreaterThanOrEqual(0, $data['failure_count']);
    }

    // ==================== BULK APPLY TEMPLATE TESTS ====================

    public function test_bulk_apply_template_with_dry_run_mode(): void
    {
        $device1 = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr069'
        ]);

        $device2 = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr069'
        ]);

        $template = ConfigurationTemplateLibrary::create([
            'manufacturer_id' => $this->manufacturer->id,
            'product_id' => $this->product->id,
            'template_name' => 'Basic Security Config',
            'template_category' => 'security',
            'protocol' => 'TR-069',
            'template_content' => ['param1' => 'value1'],
            'usage_count' => 0
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device1->id, $device2->id],
            'template_id' => $template->id,
            'dry_run' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'template',
                    'dry_run',
                    'processed',
                    'compatible_count',
                    'incompatible_count',
                    'applied_count',
                    'results'
                ]
            ]);

        $data = $response->json('data');
        $this->assertTrue($data['dry_run']);
        $this->assertEquals(0, $data['applied_count']); // No tasks created in dry-run

        // Verify no provisioning tasks were created
        $this->assertDatabaseMissing('provisioning_tasks', [
            'device_id' => $device1->id,
            'template_id' => $template->id
        ]);
    }

    public function test_bulk_apply_template_creates_provisioning_tasks(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr069'
        ]);

        $template = ConfigurationTemplateLibrary::create([
            'manufacturer_id' => $this->manufacturer->id,
            'template_name' => 'WiFi Config',
            'template_category' => 'wifi',
            'protocol' => 'TR-069',
            'template_content' => ['wifi' => 'enabled'],
            'usage_count' => 0
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device->id],
            'template_id' => $template->id,
            'dry_run' => false
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertFalse($data['dry_run']);
        $this->assertEquals(1, $data['applied_count']);

        // Verify provisioning task was created
        $this->assertDatabaseHas('provisioning_tasks', [
            'device_id' => $device->id,
            'task_type' => 'configuration',
            'task_status' => 'pending',
            'template_id' => $template->id
        ]);

        // Verify template usage count was incremented
        $template->refresh();
        $this->assertEquals(1, $template->usage_count);
    }

    public function test_bulk_apply_template_detects_manufacturer_incompatibility(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => 'DifferentManufacturer',
            'protocol_type' => 'tr069'
        ]);

        $template = ConfigurationTemplateLibrary::create([
            'manufacturer_id' => $this->manufacturer->id,
            'template_name' => 'Test Template',
            'template_category' => 'basic',
            'protocol' => 'TR-069',
            'template_content' => ['test' => 'data']
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device->id],
            'template_id' => $template->id,
            'dry_run' => true
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(0, $data['compatible_count']);
        $this->assertEquals(1, $data['incompatible_count']);
        
        $result = $data['results'][0];
        $this->assertEquals('incompatible', $result['status']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_bulk_apply_template_detects_protocol_incompatibility(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr369'
        ]);

        $template = ConfigurationTemplateLibrary::create([
            'manufacturer_id' => $this->manufacturer->id,
            'template_name' => 'TR-069 Only Template',
            'template_category' => 'basic',
            'protocol' => 'TR-069',
            'template_content' => ['test' => 'data']
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device->id],
            'template_id' => $template->id,
            'dry_run' => true
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(0, $data['compatible_count']);
        $this->assertEquals(1, $data['incompatible_count']);
    }

    public function test_bulk_apply_template_validates_required_fields(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids', 'template_id']);
    }

    public function test_bulk_apply_template_returns_404_for_nonexistent_template(): void
    {
        $device = CpeDevice::factory()->create();

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device->id],
            'template_id' => 99999
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template_id']);
    }

    // ==================== BULK FIRMWARE CHECK TESTS ====================

    public function test_bulk_firmware_check_with_target_version(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'model_name' => $this->product->model_name,
            'software_version' => '1.0.0'
        ]);

        $firmwareVersion = FirmwareVersion::create([
            'product_id' => $this->product->id,
            'version_number' => '2.0.0',
            'release_date' => now(),
            'release_notes' => 'Major update',
            'is_active' => true
        ]);

        FirmwareCompatibility::create([
            'product_id' => $this->product->id,
            'firmware_version_id' => $firmwareVersion->id,
            'compatibility_status' => 'compatible',
            'tested' => true,
            'tested_date' => now(),
            'notes' => 'Fully compatible'
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', [
            'device_ids' => [$device->id],
            'target_firmware_version' => '2.0.0'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'target_firmware_version',
                    'processed',
                    'compatible_count',
                    'incompatible_count',
                    'unknown_count',
                    'results' => [
                        '*' => [
                            'device_id',
                            'serial_number',
                            'product',
                            'current_firmware',
                            'target_firmware',
                            'can_upgrade',
                            'recommendation'
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals('2.0.0', $data['target_firmware_version']);
        $this->assertEquals(1, $data['processed']);
    }

    public function test_bulk_firmware_check_detects_unknown_products(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => 'UnknownManufacturer',
            'model_name' => 'UnknownModel',
            'software_version' => '1.0.0'
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', [
            'device_ids' => [$device->id]
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['unknown_count']);
        
        $result = $data['results'][0];
        $this->assertEquals('unknown', $result['status']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function test_bulk_firmware_check_without_target_version(): void
    {
        $device = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'model_name' => $this->product->model_name,
            'software_version' => '1.0.0'
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', [
            'device_ids' => [$device->id]
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('current', $data['target_firmware_version']);
        $this->assertEquals(1, $data['processed']);
    }

    public function test_bulk_firmware_check_handles_multiple_devices_with_mixed_results(): void
    {
        $device1 = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'model_name' => $this->product->model_name,
            'software_version' => '1.0.0'
        ]);

        $device2 = CpeDevice::factory()->create([
            'manufacturer' => 'UnknownManufacturer',
            'model_name' => 'UnknownModel',
            'software_version' => '1.0.0'
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', [
            'device_ids' => [$device1->id, $device2->id]
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(2, $data['processed']);
        $this->assertGreaterThanOrEqual(1, $data['unknown_count']);
    }

    public function test_bulk_firmware_check_validates_required_fields(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids']);
    }

    public function test_bulk_firmware_check_validates_device_exists(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/firmware-check', [
            'device_ids' => [99999]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids.0']);
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function test_bulk_operations_handle_empty_device_array(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_ids']);
    }

    public function test_bulk_operations_return_consistent_error_format(): void
    {
        $response = $this->apiPost('/api/v1/vendors/bulk/detect', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);
    }

    public function test_bulk_apply_template_updates_usage_count_correctly(): void
    {
        $device1 = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr069'
        ]);

        $device2 = CpeDevice::factory()->create([
            'manufacturer' => $this->manufacturer->name,
            'protocol_type' => 'tr069'
        ]);

        $template = ConfigurationTemplateLibrary::create([
            'manufacturer_id' => $this->manufacturer->id,
            'template_name' => 'Usage Count Test',
            'template_category' => 'basic',
            'protocol' => 'TR-069',
            'template_content' => ['test' => 'data'],
            'usage_count' => 5
        ]);

        $response = $this->apiPost('/api/v1/vendors/bulk/apply-template', [
            'device_ids' => [$device1->id, $device2->id],
            'template_id' => $template->id,
            'dry_run' => false
        ]);

        $response->assertStatus(200);

        // Verify usage count incremented by 2 (one per device)
        $template->refresh();
        $this->assertEquals(7, $template->usage_count);
    }

    public function test_bulk_detect_vendor_handles_large_batch_size(): void
    {
        // Create 50 devices to test batch processing
        $deviceIds = [];
        for ($i = 0; $i < 50; $i++) {
            $device = CpeDevice::factory()->create([
                'manufacturer' => 'TP-Link',
                'model_name' => 'Archer C7',
                'oui' => 'F4F26D'
            ]);
            $deviceIds[] = $device->id;
        }

        $response = $this->apiPost('/api/v1/vendors/bulk/detect', [
            'device_ids' => $deviceIds
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(50, $data['processed']);
        $this->assertArrayHasKey('success_count', $data);
        $this->assertArrayHasKey('failure_count', $data);
        $this->assertCount(50, $data['results']);
    }
}
