<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    private TenantAnomalyDetector $detector;
    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->detector = new TenantAnomalyDetector();
        
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_detects_cross_tenant_token_reuse()
    {
        $tokenId = 'test-token-123';
        
        $result1 = $this->detector->detectCrossTenantTokenReuse($tokenId, $this->tenant->id);
        $this->assertFalse($result1);
        
        $otherTenant = Tenant::factory()->create();
        $result2 = $this->detector->detectCrossTenantTokenReuse($tokenId, $otherTenant->id);
        $this->assertTrue($result2);
    }

    public function test_detects_ip_anomaly_for_user()
    {
        $normalIp = '192.168.1.100';
        
        for ($i = 0; $i < 5; $i++) {
            $this->detector->recordUserIp($this->user->id, $normalIp);
        }
        
        $result1 = $this->detector->detectIpAnomaly($this->user->id, $normalIp);
        $this->assertFalse($result1);
        
        $newIp = '10.0.0.50';
        $result2 = $this->detector->detectIpAnomaly($this->user->id, $newIp);
        $this->assertTrue($result2);
    }

    public function test_tracks_failed_auth_attempts()
    {
        $ip = '192.168.1.100';
        
        for ($i = 0; $i < 4; $i++) {
            $this->detector->recordFailedAuth($ip, $this->tenant->id);
        }
        
        $result1 = $this->detector->isRateLimited($ip, $this->tenant->id);
        $this->assertFalse($result1);
        
        $this->detector->recordFailedAuth($ip, $this->tenant->id);
        
        $result2 = $this->detector->isRateLimited($ip, $this->tenant->id);
        $this->assertTrue($result2);
    }

    public function test_resets_failed_auth_on_success()
    {
        $ip = '192.168.1.100';
        
        for ($i = 0; $i < 5; $i++) {
            $this->detector->recordFailedAuth($ip, $this->tenant->id);
        }
        
        $this->assertTrue($this->detector->isRateLimited($ip, $this->tenant->id));
        
        $this->detector->recordSuccessfulAuth($ip, $this->tenant->id);
        
        $this->assertFalse($this->detector->isRateLimited($ip, $this->tenant->id));
    }

    public function test_anomaly_detection_respects_tenant_isolation()
    {
        $ip = '192.168.1.100';
        $tenant2 = Tenant::factory()->create();
        
        for ($i = 0; $i < 5; $i++) {
            $this->detector->recordFailedAuth($ip, $this->tenant->id);
        }
        
        $this->assertTrue($this->detector->isRateLimited($ip, $this->tenant->id));
        $this->assertFalse($this->detector->isRateLimited($ip, $tenant2->id));
    }
}
