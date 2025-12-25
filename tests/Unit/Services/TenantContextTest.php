<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    protected function tearDown(): void
    {
        $this->context->clear();
        parent::tearDown();
    }

    public function test_can_set_and_get_tenant()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->context->set($tenant);

        $this->assertNotNull($this->context->get());
        $this->assertEquals($tenant->id, $this->context->get()->id);
    }

    public function test_can_get_tenant_id()
    {
        $tenant = Tenant::factory()->create();
        $this->context->set($tenant);

        $this->assertEquals($tenant->id, $this->context->id());
    }

    public function test_returns_null_when_no_tenant_set()
    {
        $this->assertNull($this->context->get());
        $this->assertNull($this->context->id());
    }

    public function test_has_tenant_returns_correct_value()
    {
        $this->assertFalse($this->context->has());

        $tenant = Tenant::factory()->create();
        $this->context->set($tenant);

        $this->assertTrue($this->context->has());
    }

    public function test_can_clear_tenant()
    {
        $tenant = Tenant::factory()->create();
        $this->context->set($tenant);
        $this->assertTrue($this->context->has());

        $this->context->clear();
        $this->assertFalse($this->context->has());
    }

    public function test_run_as_executes_in_tenant_context()
    {
        $tenant = Tenant::factory()->create();
        $originalTenant = Tenant::factory()->create();
        
        $this->context->set($originalTenant);
        
        $result = $this->context->runAs($tenant, function () use ($tenant) {
            return $this->context->id() === $tenant->id;
        });
        
        $this->assertTrue($result);
        $this->assertEquals($originalTenant->id, $this->context->id());
    }

    public function test_run_as_restores_context_on_exception()
    {
        $tenant = Tenant::factory()->create();
        $originalTenant = Tenant::factory()->create();
        
        $this->context->set($originalTenant);
        
        try {
            $this->context->runAs($tenant, function () {
                throw new \Exception('Test exception');
            });
        } catch (\Exception $e) {
        }
        
        $this->assertEquals($originalTenant->id, $this->context->id());
    }
}
