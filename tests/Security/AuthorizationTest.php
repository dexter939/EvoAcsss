<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Authorization & Access Control Tests
 * 
 * Tests RBAC and multi-tenant device access control.
 * OWASP A01:2021 - Broken Access Control
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $viewer;
    private User $manager;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users with different roles
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->viewer = User::factory()->create(['email' => 'viewer@test.com']);
        $this->manager = User::factory()->create(['email' => 'manager@test.com']);
        
        $this->device = Device::factory()->create([
            'serial_number' => 'TEST-AUTH-001',
        ]);
    }

    /** @test */
    public function it_prevents_unauthorized_device_access()
    {
        // User without device access
        $unauthorizedUser = User::factory()->create();
        
        $this->actingAs($unauthorizedUser);

        $response = $this->getJson("/api/v1/devices/{$this->device->id}");
        
        // Should deny access (403) or not found (404)
        $this->assertContains($response->status(), [403, 404], 
            'Unauthorized user should not access device');
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        // Create two tenants
        $tenant1User = User::factory()->create();
        $tenant2User = User::factory()->create();
        
        $tenant1Device = Device::factory()->create(['serial_number' => 'TENANT1-DEVICE']);
        $tenant2Device = Device::factory()->create(['serial_number' => 'TENANT2-DEVICE']);
        
        // Assign devices to tenants
        $tenant1User->devices()->attach($tenant1Device->id, ['permission' => 'admin']);
        $tenant2User->devices()->attach($tenant2Device->id, ['permission' => 'admin']);
        
        // Tenant 1 tries to access Tenant 2's device
        $this->actingAs($tenant1User);
        $response = $this->getJson("/api/v1/devices/{$tenant2Device->id}");
        
        $this->assertContains($response->status(), [403, 404], 
            'Tenant should not access other tenant devices');
    }

    /** @test */
    public function it_prevents_privilege_escalation()
    {
        $viewer = User::factory()->create();
        $device = Device::factory()->create();
        
        // Assign viewer permission
        $viewer->devices()->attach($device->id, ['permission' => 'viewer']);
        
        $this->actingAs($viewer);
        
        // Try to delete device (admin action)
        $response = $this->deleteJson("/api/v1/devices/{$device->id}");
        
        $this->assertContains($response->status(), [403, 405], 
            'Viewer should not be able to delete devices');
        
        // Verify device still exists
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    /** @test */
    public function it_prevents_horizontal_privilege_escalation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // User 1 tries to update User 2's profile
        $this->actingAs($user1);
        
        $response = $this->putJson("/api/v1/users/{$user2->id}", [
            'name' => 'Hacked Name',
        ]);
        
        $this->assertContains($response->status(), [403, 404], 
            'User should not modify other user profiles');
        
        $user2->refresh();
        $this->assertNotEquals('Hacked Name', $user2->name);
    }

    /** @test */
    public function it_prevents_insecure_direct_object_references()
    {
        $user = User::factory()->create();
        $otherUserDevice = Device::factory()->create();
        
        $this->actingAs($user);
        
        // Try sequential ID guessing
        for ($id = 1; $id <= 10; $id++) {
            $response = $this->getJson("/api/v1/devices/{$id}");
            
            // Should not leak device information
            if ($response->status() === 200) {
                // If accessible, user must have explicit permission
                $this->assertTrue(
                    $user->devices->contains('id', $id),
                    "User accessed device ID {$id} without permission"
                );
            }
        }
    }

    /** @test */
    public function it_validates_device_access_on_bulk_operations()
    {
        $user = User::factory()->create();
        $authorizedDevice = Device::factory()->create();
        $unauthorizedDevice = Device::factory()->create();
        
        $user->devices()->attach($authorizedDevice->id, ['permission' => 'admin']);
        
        $this->actingAs($user);
        
        // Try bulk operation including unauthorized device
        $response = $this->postJson('/api/v1/devices/batch', [
            'device_ids' => [$authorizedDevice->id, $unauthorizedDevice->id],
            'action' => 'reboot',
        ]);
        
        // Should reject or only process authorized devices
        $this->assertContains($response->status(), [200, 403, 422]);
        
        if ($response->status() === 200) {
            $data = $response->json();
            // Should not process unauthorized device
            $processed = $data['processed'] ?? [];
            $this->assertNotContains($unauthorizedDevice->id, $processed);
        }
    }

    /** @test */
    public function it_prevents_api_key_abuse()
    {
        $user = User::factory()->create();
        
        // Create API key
        $apiKey = $user->createToken('test-key')->plainTextToken;
        
        // Use API key to access resources
        $response = $this->withHeader('Authorization', "Bearer {$apiKey}")
            ->getJson('/api/v1/devices');
        
        $this->assertContains($response->status(), [200, 401]);
        
        // Try to use API key after deletion
        $user->tokens()->delete();
        
        $response = $this->withHeader('Authorization', "Bearer {$apiKey}")
            ->getJson('/api/v1/devices');
        
        $this->assertEquals(401, $response->status(), 
            'Deleted API key should not work');
    }

    /** @test */
    public function it_enforces_permission_levels_correctly()
    {
        $device = Device::factory()->create();
        
        // Test viewer permissions
        $viewer = User::factory()->create();
        $viewer->devices()->attach($device->id, ['permission' => 'viewer']);
        
        $this->actingAs($viewer);
        
        // Viewer can read
        $response = $this->getJson("/api/v1/devices/{$device->id}");
        $this->assertEquals(200, $response->status());
        
        // Viewer cannot write
        $response = $this->putJson("/api/v1/devices/{$device->id}", [
            'description' => 'Modified',
        ]);
        $this->assertContains($response->status(), [403, 405]);
        
        // Test manager permissions
        $manager = User::factory()->create();
        $manager->devices()->attach($device->id, ['permission' => 'manager']);
        
        $this->actingAs($manager);
        
        // Manager can read and write
        $response = $this->putJson("/api/v1/devices/{$device->id}", [
            'description' => 'Manager Modified',
        ]);
        $this->assertContains($response->status(), [200, 422]);
    }

    /** @test */
    public function it_prevents_mass_assignment_vulnerabilities()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);
        
        // Try to mass assign protected fields
        $response = $this->postJson('/api/v1/devices', [
            'serial_number' => 'TEST-MASS-ASSIGN',
            'device_type' => 'ONT',
            'is_admin' => true, // Should not be mass assignable
            'role' => 'super_admin', // Should not be mass assignable
            'tenant_id' => 999, // Should not be mass assignable
        ]);
        
        if ($response->status() === 201) {
            $device = Device::latest()->first();
            
            // Protected fields should not be set
            $this->assertNull($device->is_admin ?? null);
            $this->assertNull($device->role ?? null);
        }
    }
}
