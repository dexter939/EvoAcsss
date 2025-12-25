<?php

namespace Tests\Unit\Services;

use App\Contexts\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TenantAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    private TenantAnomalyDetector $detector;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->detector = new TenantAnomalyDetector();
        
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        TenantContext::set($this->tenant);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_records_failed_auth_attempt()
    {
        $request = Request::create('/api/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        
        $this->detector->recordFailedAuthAttempt($request, $this->tenant->id);
        
        $cacheKey = "anomaly:failed_auth_attempts:{$this->tenant->id}:192.168.1.100";
        $count = Cache::get($cacheKey, 0);
        
        $this->assertEquals(1, $count);
    }

    public function test_records_rate_limit_violation()
    {
        $request = Request::create('/api/v1/devices', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        
        $this->detector->recordRateLimitViolation($request, $this->tenant->id);
        
        $cacheKey = "anomaly:rate_limit_violations:{$this->tenant->id}:192.168.1.100";
        $count = Cache::get($cacheKey, 0);
        
        $this->assertEquals(1, $count);
    }

    public function test_accumulates_failed_auth_attempts()
    {
        $request = Request::create('/api/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        
        for ($i = 0; $i < 3; $i++) {
            $this->detector->recordFailedAuthAttempt($request, $this->tenant->id);
        }
        
        $cacheKey = "anomaly:failed_auth_attempts:{$this->tenant->id}:192.168.1.100";
        $count = Cache::get($cacheKey, 0);
        
        $this->assertEquals(3, $count);
    }

    public function test_tenant_isolation_for_anomaly_counters()
    {
        $tenant2 = Tenant::factory()->create();
        
        $request = Request::create('/api/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        
        $this->detector->recordFailedAuthAttempt($request, $this->tenant->id);
        $this->detector->recordFailedAuthAttempt($request, $this->tenant->id);
        
        $this->detector->recordFailedAuthAttempt($request, $tenant2->id);
        
        $cacheKey1 = "anomaly:failed_auth_attempts:{$this->tenant->id}:192.168.1.100";
        $cacheKey2 = "anomaly:failed_auth_attempts:{$tenant2->id}:192.168.1.100";
        
        $this->assertEquals(2, Cache::get($cacheKey1, 0));
        $this->assertEquals(1, Cache::get($cacheKey2, 0));
    }

    public function test_uses_tenant_context_when_no_tenant_provided()
    {
        $request = Request::create('/api/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        
        $this->detector->recordFailedAuthAttempt($request);
        
        $cacheKey = "anomaly:failed_auth_attempts:{$this->tenant->id}:192.168.1.100";
        $count = Cache::get($cacheKey, 0);
        
        $this->assertEquals(1, $count);
    }
}
