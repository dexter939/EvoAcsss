<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Cross-Site Scripting (XSS) Protection Tests
 * 
 * Tests protection against XSS attacks in user inputs and outputs.
 * OWASP A03:2021 - Injection
 */
class XssProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
    }

    /** @test */
    public function it_sanitizes_device_description_input()
    {
        $this->actingAs($this->admin);

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg/onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')">',
            '<body onload=alert("XSS")>',
            '<input onfocus=alert("XSS") autofocus>',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->postJson('/api/v1/devices', [
                'serial_number' => 'TEST-' . uniqid(),
                'device_type' => 'ONT',
                'description' => $payload,
            ]);

            if ($response->status() === 201) {
                $device = Device::latest()->first();
                
                // Description should be sanitized or escaped
                $this->assertStringNotContainsString('<script', $device->description);
                $this->assertStringNotContainsString('javascript:', $device->description);
                $this->assertStringNotContainsString('onerror=', $device->description);
                $this->assertStringNotContainsString('onload=', $device->description);
            }
        }
    }

    /** @test */
    public function it_escapes_output_in_api_responses()
    {
        $this->actingAs($this->admin);

        $device = Device::factory()->create([
            'serial_number' => 'XSS-TEST',
            'description' => '<script>alert("XSS")</script>Test Device',
        ]);

        $response = $this->getJson("/api/v1/devices/{$device->id}");
        
        $this->assertEquals(200, $response->status());
        
        $content = $response->getContent();
        
        // Raw script tags should not be in JSON response
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $content);
    }

    /** @test */
    public function it_prevents_dom_based_xss_in_json_responses()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/devices?search=<script>alert(1)</script>');
        
        $this->assertEquals(200, $response->status());
        
        // Response should be valid JSON
        $this->assertJson($response->getContent());
        
        // Should not contain unescaped script tags
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>', $content);
    }

    /** @test */
    public function it_sets_correct_content_security_policy_headers()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/dashboard');
        
        // Check for security headers
        $headers = $response->headers;
        
        // X-XSS-Protection header (legacy but still useful)
        $this->assertTrue(
            $headers->has('X-XSS-Protection') || $headers->has('Content-Security-Policy'),
            'Should have XSS protection headers'
        );
        
        // X-Content-Type-Options
        $this->assertEquals('nosniff', $headers->get('X-Content-Type-Options'));
    }

    /** @test */
    public function it_prevents_xss_in_configuration_templates()
    {
        $this->actingAs($this->admin);

        $payload = [
            'name' => '<script>alert("XSS")</script>Malicious Template',
            'description' => '<img src=x onerror=alert(1)>',
            'template_data' => [
                'parameter' => 'javascript:void(0)',
            ],
        ];

        $response = $this->postJson('/api/v1/templates', $payload);
        
        if ($response->status() === 201) {
            $template = \App\Models\ConfigurationTemplate::latest()->first();
            
            // Template name should be sanitized
            $this->assertStringNotContainsString('<script', $template->name);
            $this->assertStringNotContainsString('javascript:', json_encode($template->template_data));
        }
    }

    /** @test */
    public function it_prevents_stored_xss_in_user_profiles()
    {
        $user = User::factory()->create([
            'name' => '<script>alert("XSS")</script>Admin',
            'email' => 'xss@test.com',
        ]);

        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/users');
        
        $content = $response->getContent();
        
        // Should not contain raw script tags
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $content);
    }

    /** @test */
    public function it_validates_file_upload_content_types()
    {
        $this->actingAs($this->admin);

        // Create a fake "image" file with HTML content
        $fakeImage = \Illuminate\Http\UploadedFile::fake()->create(
            'malicious.jpg',
            100,
            'text/html'
        );

        $response = $this->postJson('/api/v1/firmware/upload', [
            'file' => $fakeImage,
        ]);

        // Should reject files with incorrect MIME types
        $this->assertContains($response->status(), [400, 422]);
    }

    /** @test */
    public function it_prevents_xss_in_error_messages()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/devices', [
            'serial_number' => '<script>alert("XSS")</script>',
            'device_type' => 'INVALID<script>',
        ]);

        $content = $response->getContent();
        
        // Error messages should not reflect raw script tags
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $content);
    }
}
