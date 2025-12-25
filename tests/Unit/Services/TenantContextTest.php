<?php

namespace Tests\Unit\Services;

use App\Contexts\TenantContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_can_set_and_get_tenant()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        TenantContext::set($tenant);

        $this->assertNotNull(TenantContext::get());
        $this->assertEquals($tenant->id, TenantContext::get()->id);
    }

    public function test_can_get_tenant_id()
    {
        $tenant = Tenant::factory()->create();
        TenantContext::set($tenant);

        $this->assertEquals($tenant->id, TenantContext::id());
    }

    public function test_returns_null_when_no_tenant_set()
    {
        $this->assertNull(TenantContext::get());
        $this->assertNull(TenantContext::id());
    }

    public function test_check_returns_correct_value()
    {
        $this->assertFalse(TenantContext::check());

        $tenant = Tenant::factory()->create();
        TenantContext::set($tenant);

        $this->assertTrue(TenantContext::check());
    }

    public function test_can_clear_tenant()
    {
        $tenant = Tenant::factory()->create();
        TenantContext::set($tenant);
        $this->assertTrue(TenantContext::check());

        TenantContext::clear();
        $this->assertFalse(TenantContext::check());
    }

    public function test_can_get_tenant_slug()
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'my-test-tenant',
        ]);
        TenantContext::set($tenant);

        $this->assertEquals('my-test-tenant', TenantContext::slug());
    }

    public function test_is_method_compares_tenants()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        TenantContext::set($tenant1);
        
        $this->assertTrue(TenantContext::is($tenant1));
        $this->assertFalse(TenantContext::is($tenant2));
    }

    public function test_require_throws_when_no_tenant()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tenant context available');
        
        TenantContext::require();
    }

    public function test_require_returns_tenant_when_set()
    {
        $tenant = Tenant::factory()->create();
        TenantContext::set($tenant);
        
        $result = TenantContext::require();
        
        $this->assertEquals($tenant->id, $result->id);
    }
}
