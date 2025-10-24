<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SQL Injection Security Tests
 * 
 * Tests protection against SQL injection attacks across all input vectors.
 * OWASP A03:2021 - Injection
 */
class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
        ]);
        
        $this->device = Device::factory()->create([
            'serial_number' => 'TEST-DEVICE-001',
            'device_type' => 'ONT',
        ]);
    }

    /** @test */
    public function it_prevents_sql_injection_in_device_search()
    {
        $this->actingAs($this->admin);

        // POSITIVE CONTROL: Create device with known serial
        $legitDevice = Device::factory()->create(['serial_number' => 'LEGIT-DEVICE-123']);
        $otherDevice = Device::factory()->create(['serial_number' => 'OTHER-DEVICE-456']);

        // STEP 1: Verify legitimate search works (positive control)
        $legitResponse = $this->getJson("/api/v1/devices?search=LEGIT");
        $this->assertEquals(200, $legitResponse->status());
        $legitData = $legitResponse->json('data');
        
        // Should find the legitimate device
        $this->assertNotEmpty($legitData, 
            "Legitimate search should return results (positive control check)");
        
        $foundSerials = array_column($legitData, 'serial_number');
        $this->assertContains('LEGIT-DEVICE-123', $foundSerials,
            "Legitimate search should find 'LEGIT-DEVICE-123'");

        // STEP 2: Test injection payloads (negative control)
        $injectionPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE devices; --",
            "' UNION SELECT * FROM users --",
            "1' AND 1=1 UNION SELECT null, username, password FROM users--",
            "admin'--",
            "' OR 1=1--",
            "1; DELETE FROM devices WHERE '1'='1",
        ];

        foreach ($injectionPayloads as $payload) {
            $response = $this->getJson("/api/v1/devices?search={$payload}");
            
            // Should not crash (500 - SQL syntax error indicating injection reached DB)
            $this->assertNotEquals(500, $response->status(), 
                "SQL injection payload caused server error: {$payload}");
            
            // Should return safe response
            $this->assertContains($response->status(), [200, 400, 422], 
                "Unexpected status for payload: {$payload}");
            
            // CRITICAL: Malicious payloads MUST return empty result set
            if ($response->status() === 200) {
                $data = $response->json('data');
                
                // Since NO device has serial matching payload, result MUST be empty
                // If injection works, it would bypass WHERE and return devices
                $this->assertEmpty($data, 
                    "SQL injection bypassed sanitization - returned " . count($data ?? []) . " device(s) " .
                    "for malicious payload '{$payload}'. Expected: empty array. " .
                    "Parameterized queries should treat payload as literal string, finding no matches.");
                
                // Double-check: Should NOT contain our known devices
                if (!empty($data)) {
                    $returnedSerials = array_column($data, 'serial_number');
                    $this->assertNotContains('LEGIT-DEVICE-123', $returnedSerials,
                        "SQL injection returned known device - WHERE clause was bypassed");
                    $this->assertNotContains('OTHER-DEVICE-456', $returnedSerials,
                        "SQL injection returned known device - WHERE clause was bypassed");
                }
            }
            
            // Verify database integrity (tables not dropped)
            $this->assertDatabaseHas('devices', [
                'serial_number' => 'LEGIT-DEVICE-123',
            ]);
            $this->assertDatabaseHas('devices', [
                'serial_number' => 'OTHER-DEVICE-456',
            ]);
        }
        
        // STEP 3: Verify legitimate search still works after injection attempts
        $finalResponse = $this->getJson("/api/v1/devices?search=LEGIT");
        $this->assertEquals(200, $finalResponse->status());
        $finalData = $finalResponse->json('data');
        $this->assertNotEmpty($finalData, 
            "Legitimate search should still work after injection attempts");
    }

    /** @test */
    public function it_prevents_sql_injection_in_device_filters()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/v1/devices?device_type=' OR '1'='1");
        
        $this->assertNotEquals(500, $response->status());
        
        // Should use parameterized queries and return empty result or validation error
        if ($response->status() === 200) {
            $data = $response->json('data');
            // Should not return all devices
            $this->assertIsArray($data);
        }
    }

    /** @test */
    public function it_prevents_sql_injection_in_tr069_parameters()
    {
        $this->actingAs($this->admin);

        $payload = [
            'device_id' => $this->device->id,
            'parameters' => [
                "Device.'; DROP TABLE tr181_parameters; --" => 'value',
            ],
        ];

        $response = $this->postJson('/api/v1/tr069/set-parameters', $payload);
        
        $this->assertNotEquals(500, $response->status());
        
        // Verify tr181_parameters table still exists
        $this->assertTrue(
            \Schema::hasTable('tr181_parameters'),
            'tr181_parameters table should not be dropped'
        );
    }

    /** @test */
    public function it_prevents_sql_injection_in_login()
    {
        $injectionPayloads = [
            "admin'--",
            "' OR '1'='1' --",
            "' OR '1'='1' /*",
            "admin' OR 1=1--",
        ];

        foreach ($injectionPayloads as $payload) {
            $response = $this->postJson('/login', [
                'email' => $payload,
                'password' => 'password',
            ]);
            
            // Should not authenticate
            $this->assertNotEquals(200, $response->status(), 
                "SQL injection in login succeeded with payload: {$payload}");
            
            // Should not crash
            $this->assertNotEquals(500, $response->status());
        }
    }

    /** @test */
    public function it_prevents_sql_injection_in_sorting()
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/v1/devices?sort=serial_number; DROP TABLE devices;--");
        
        $this->assertNotEquals(500, $response->status());
        
        // Verify devices table still exists
        $this->assertDatabaseHas('devices', [
            'serial_number' => 'TEST-DEVICE-001',
        ]);
    }

    /** @test */
    public function it_uses_prepared_statements_for_queries()
    {
        $this->actingAs($this->admin);

        // Enable query logging
        \DB::enableQueryLog();

        $this->getJson("/api/v1/devices?search=' OR '1'='1");

        $queries = \DB::getQueryLog();
        
        // All queries should use bindings (prepared statements)
        foreach ($queries as $query) {
            if (str_contains($query['query'], 'select') && 
                str_contains($query['query'], 'devices')) {
                $this->assertNotEmpty($query['bindings'] ?? [], 
                    "Query should use prepared statements: {$query['query']}");
            }
        }

        \DB::disableQueryLog();
    }

    /** @test */
    public function it_prevents_blind_sql_injection_timing_attacks()
    {
        $this->actingAs($this->admin);

        // Time-based blind SQL injection payload
        $payload = "' AND SLEEP(5)--";
        
        $start = microtime(true);
        $response = $this->getJson("/api/v1/devices?search={$payload}");
        $duration = microtime(true) - $start;
        
        // Should not execute SLEEP command
        $this->assertLessThan(2, $duration, 
            "Query took too long - possible blind SQL injection: {$duration}s");
        
        $this->assertNotEquals(500, $response->status());
    }

    /** @test */
    public function it_prevents_sql_injection_in_batch_operations()
    {
        $this->actingAs($this->admin);

        $payload = [
            'device_ids' => [
                $this->device->id,
                "'; DROP TABLE devices; --",
                "1 OR 1=1",
            ],
            'action' => 'reboot',
        ];

        $response = $this->postJson('/api/v1/devices/batch', $payload);
        
        // Should validate input types
        $this->assertContains($response->status(), [200, 400, 422]);
        
        // Verify database integrity
        $this->assertDatabaseHas('devices', [
            'serial_number' => 'TEST-DEVICE-001',
        ]);
    }
}
