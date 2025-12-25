<?php

namespace Tests\Feature\MultiTenant;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SecurityAlertService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityAlertTest extends TestCase
{

    private SecurityAlertService $alertService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->alertService = app(SecurityAlertService::class);
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    }

    public function test_can_send_critical_alert()
    {
        Log::shouldReceive('channel')
            ->with('security')
            ->andReturnSelf();
        Log::shouldReceive('critical')
            ->once();
        
        $this->alertService->alert(
            type: 'cross_tenant_access',
            message: 'Cross-tenant access attempt detected',
            severity: 'critical',
            context: [
                'tenant_id' => $this->tenant->id,
                'ip' => '192.168.1.100',
            ]
        );
        
        $this->assertTrue(true);
    }

    public function test_can_send_warning_alert()
    {
        Log::shouldReceive('channel')
            ->with('security')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once();
        
        $this->alertService->alert(
            type: 'failed_auth',
            message: 'Multiple failed authentication attempts',
            severity: 'warning',
            context: [
                'tenant_id' => $this->tenant->id,
                'attempts' => 5,
            ]
        );
        
        $this->assertTrue(true);
    }

    public function test_alert_includes_tenant_context()
    {
        $context = [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'user_id' => 123,
        ];
        
        Log::shouldReceive('channel')
            ->with('security')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->withArgs(function ($message, $logContext) use ($context) {
                return isset($logContext['tenant_id']) 
                    && $logContext['tenant_id'] === $context['tenant_id'];
            })
            ->once();
        
        $this->alertService->alert(
            type: 'info',
            message: 'Info alert',
            severity: 'info',
            context: $context
        );
        
        $this->assertTrue(true);
    }

    public function test_stores_alert_to_database_when_enabled()
    {
        config(['security.alerts.store_to_database' => true]);
        
        $this->alertService->alert(
            type: 'test_alert',
            message: 'Test alert for database storage',
            severity: 'major',
            context: ['tenant_id' => $this->tenant->id]
        );
        
        if (config('security.alerts.store_to_database', false)) {
            $this->assertDatabaseHas('security_alerts', [
                'type' => 'test_alert',
            ]);
        } else {
            $this->assertTrue(true);
        }
    }
}
