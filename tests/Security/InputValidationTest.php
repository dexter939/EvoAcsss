<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Input Validation Security Tests
 * 
 * Tests input validation across all endpoints.
 * OWASP A03:2021 - Injection
 */
class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create();
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices', []);

        $this->assertEquals(422, $response->status());
        $this->assertArrayHasKey('errors', $response->json());
    }

    /** @test */
    public function it_validates_data_types()
    {
        $this->actingAs($this->admin);

        $invalidInputs = [
            ['device_id' => 'not-a-number'],
            ['port' => 'invalid-port'],
            ['enabled' => 'not-boolean'],
            ['timeout' => 'not-numeric'],
        ];

        foreach ($invalidInputs as $input) {
            $response = $this->postJson('/api/v1/devices', $input);
            
            $this->assertContains($response->status(), [400, 422], 
                'Should reject invalid data types: ' . json_encode($input));
        }
    }

    /** @test */
    public function it_validates_string_lengths()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices', [
            'serial_number' => str_repeat('A', 1000),
            'device_type' => 'ONT',
        ]);

        $this->assertEquals(422, $response->status(), 
            'Should reject strings exceeding maximum length');
    }

    /** @test */
    public function it_validates_numeric_ranges()
    {
        $this->actingAs($this->admin);

        $invalidRanges = [
            ['port' => -1],
            ['port' => 99999],
            ['timeout' => -100],
            ['max_connections' => 999999],
        ];

        foreach ($invalidRanges as $input) {
            $response = $this->postJson('/api/v1/devices', array_merge([
                'serial_number' => 'TEST-' . uniqid(),
                'device_type' => 'ONT',
            ], $input));
            
            $this->assertContains($response->status(), [400, 422], 
                'Should reject out-of-range values: ' . json_encode($input));
        }
    }

    /** @test */
    public function it_validates_email_formats()
    {
        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'user@',
            'user @example.com',
            'user@example',
            '<script>alert(1)</script>@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->postJson('/register', [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $this->assertEquals(422, $response->status(), 
                "Should reject invalid email: {$email}");
        }
    }

    /** @test */
    public function it_validates_url_formats()
    {
        $this->actingAs($this->admin);

        $invalidUrls = [
            'not-a-url',
            'ftp://invalid-protocol.com',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '//evil.com',
        ];

        foreach ($invalidUrls as $url) {
            $response = $this->postJson('/api/v1/connection-request', [
                'device_id' => Device::factory()->create()->id,
                'url' => $url,
            ]);

            $this->assertContains($response->status(), [400, 422], 
                "Should reject invalid URL: {$url}");
        }
    }

    /** @test */
    public function it_validates_ip_address_formats()
    {
        $this->actingAs($this->admin);

        $invalidIps = [
            '256.1.1.1',
            '1.1.1',
            '1.1.1.1.1',
            'not-an-ip',
            '192.168.1.1; DROP TABLE devices;',
        ];

        foreach ($invalidIps as $ip) {
            $response = $this->postJson('/api/v1/devices', [
                'serial_number' => 'TEST-' . uniqid(),
                'device_type' => 'ONT',
                'ip_address' => $ip,
            ]);

            $this->assertContains($response->status(), [400, 422], 
                "Should reject invalid IP: {$ip}");
        }
    }

    /** @test */
    public function it_validates_json_payloads()
    {
        $this->actingAs($this->admin);

        // Send invalid JSON
        $response = $this->postJson('/api/v1/devices', 
            '{invalid json}', 
            ['Content-Type' => 'application/json']
        );

        $this->assertEquals(400, $response->status(), 
            'Should reject malformed JSON');
    }

    /** @test */
    public function it_sanitizes_file_upload_names()
    {
        $this->actingAs($this->admin);

        $maliciousFilenames = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32\\config\\sam',
            'test.php',
            'test.exe',
            '<script>alert(1)</script>.jpg',
        ];

        foreach ($maliciousFilenames as $filename) {
            $file = \Illuminate\Http\UploadedFile::fake()->create($filename, 100);

            $response = $this->postJson('/api/v1/firmware/upload', [
                'file' => $file,
            ]);

            // Should reject or sanitize
            $this->assertContains($response->status(), [200, 400, 422]);
            
            if ($response->status() === 200) {
                // File should be renamed/sanitized
                $uploadedPath = $response->json('path') ?? '';
                $this->assertStringNotContainsString('..', $uploadedPath);
                $this->assertStringNotContainsString('<script>', $uploadedPath);
            }
        }
    }

    /** @test */
    public function it_rejects_null_byte_injection()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices', [
            'serial_number' => "TEST\x00INJECTION",
            'device_type' => 'ONT',
        ]);

        $this->assertContains($response->status(), [400, 422], 
            'Should reject null byte injection');
    }

    /** @test */
    public function it_validates_array_inputs()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices/batch', [
            'device_ids' => 'not-an-array', // Should be array
            'action' => 'reboot',
        ]);

        $this->assertEquals(422, $response->status(), 
            'Should validate array types');
    }

    /** @test */
    public function it_limits_array_sizes()
    {
        $this->actingAs($this->admin);

        $hugeArray = range(1, 10000);

        $response = $this->postJson('/api/v1/devices/batch', [
            'device_ids' => $hugeArray,
            'action' => 'reboot',
        ]);

        $this->assertContains($response->status(), [400, 422], 
            'Should reject excessively large arrays');
    }

    /** @test */
    public function it_validates_nested_json_depth()
    {
        $this->actingAs($this->admin);

        // Create deeply nested JSON
        $deeplyNested = ['level' => 1];
        $current = &$deeplyNested;
        for ($i = 2; $i < 100; $i++) {
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }

        $response = $this->postJson('/api/v1/templates', [
            'name' => 'Deep Template',
            'template_data' => $deeplyNested,
        ]);

        // Should handle or reject deep nesting gracefully
        $this->assertNotEquals(500, $response->status(), 
            'Should handle deeply nested JSON without crashing');
    }

    /** @test */
    public function it_validates_allowed_enum_values()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices', [
            'serial_number' => 'TEST-ENUM',
            'device_type' => 'INVALID_TYPE_XXXXX',
        ]);

        $this->assertEquals(422, $response->status(), 
            'Should reject values not in allowed enum');
    }

    /** @test */
    public function it_prevents_path_traversal_in_inputs()
    {
        $this->actingAs($this->admin);

        $traversalPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '/etc/passwd',
            'C:\\Windows\\System32',
        ];

        foreach ($traversalPaths as $path) {
            $response = $this->postJson('/api/v1/firmware/upload', [
                'path' => $path,
            ]);

            $this->assertContains($response->status(), [400, 422], 
                "Should reject path traversal: {$path}");
        }
    }
}
