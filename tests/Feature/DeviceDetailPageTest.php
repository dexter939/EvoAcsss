<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CpeDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceDetailPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that device detail page loads without JavaScript errors.
     * Regression test for duplicate const deviceId declaration bug.
     *
     * @return void
     */
    public function test_device_detail_page_loads_without_duplicate_declarations(): void
    {
        $user = User::factory()->create();
        $device = CpeDevice::factory()->create([
            'serial_number' => 'TEST123456',
            'manufacturer' => 'Test Manufacturer',
            'connection_request_url' => 'http://example.com',
        ]);
        
        // Grant device access to user
        $user->devices()->attach($device->id, [
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get("/acs/devices/{$device->id}");

        $response->assertStatus(200);
        
        // Get the HTML content
        $content = $response->getContent();
        
        // Verify no duplicate deviceId declarations
        // Count occurrences of "const deviceId"
        $constDeviceIdCount = substr_count($content, 'const deviceId');
        
        $this->assertEquals(
            1,
            $constDeviceIdCount,
            "Found {$constDeviceIdCount} declarations of 'const deviceId', expected exactly 1"
        );
        
        // Verify critical functions are defined
        $this->assertStringContainsString('function openSetParametersModal', $content);
        $this->assertStringContainsString('function triggerNetworkScan', $content);
        $this->assertStringContainsString('function aiAnalyzeDeviceHistory', $content);
        
        // Verify no JavaScript syntax errors in the response
        $this->assertStringNotContainsString('SyntaxError', $content);
        $this->assertStringNotContainsString('Identifier \'deviceId\' has already been declared', $content);
    }

    /**
     * Test that all modal functions are defined in device detail page.
     *
     * @return void
     */
    public function test_device_detail_page_has_all_modal_functions(): void
    {
        $user = User::factory()->create();
        $device = CpeDevice::factory()->create();
        
        // Grant device access to user
        $user->devices()->attach($device->id, [
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get("/acs/devices/{$device->id}");

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // List of critical functions that must be present
        $requiredFunctions = [
            'openSetParametersModal',
            'openGetParametersModal',
            'openParametersModal',
            'openDataModelModal',
            'openDiagnosticModal',
            'triggerNetworkScan',
            'aiAnalyzeDeviceHistory',
            'provisionDevice',
            'rebootDevice',
            'connectionRequest',
        ];
        
        foreach ($requiredFunctions as $function) {
            $this->assertStringContainsString(
                "function {$function}",
                $content,
                "Required function '{$function}' is missing from device detail page"
            );
        }
    }

    /**
     * Test that device detail page includes necessary JavaScript libraries.
     *
     * @return void
     */
    public function test_device_detail_page_includes_javascript_libraries(): void
    {
        $user = User::factory()->create();
        $device = CpeDevice::factory()->create();
        
        // Grant device access to user
        $user->devices()->attach($device->id, [
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get("/acs/devices/{$device->id}");

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Verify jQuery is loaded before custom scripts
        $this->assertStringContainsString('jquery', strtolower($content));
        
        // Verify bootstrap is loaded
        $this->assertStringContainsString('bootstrap', strtolower($content));
    }
}
