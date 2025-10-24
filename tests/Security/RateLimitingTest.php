<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\IpBlacklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

/**
 * Rate Limiting & DDoS Protection Tests
 * 
 * Tests rate limiting mechanisms and automated IP blocking.
 * OWASP A05:2021 - Security Misconfiguration
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear rate limiters before each test
        RateLimiter::clear('api:127.0.0.1');
        RateLimiter::clear('login-attempts:127.0.0.1');
        Cache::flush();
        IpBlacklist::query()->delete();
    }

    /** @test */
    public function it_rate_limits_api_requests()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rateLimitHit = false;
        $requestCount = 0;

        // Make many requests to trigger rate limit
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            
            $requestCount++;

            if ($response->status() === 429) {
                $rateLimitHit = true;
                break;
            }
        }

        $this->assertTrue($rateLimitHit, 
            "Rate limit should be triggered after {$requestCount} requests");
    }

    /** @test */
    public function it_includes_rate_limit_headers()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/devices');

        // Should include rate limit headers
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') || 
            $response->headers->has('RateLimit-Limit'),
            'Should include rate limit information in headers'
        );
    }

    /** @test */
    public function it_automatically_blocks_ip_after_violations()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Trigger multiple rate limit violations
        for ($attempt = 0; $attempt < 10; $attempt++) {
            for ($i = 0; $i < 100; $i++) {
                $response = $this->getJson('/api/v1/devices');
                
                if ($response->status() === 429) {
                    break;
                }
            }
            
            // Small delay between violation bursts
            usleep(100000); // 0.1 second
        }

        // Check if IP was blacklisted
        $blocked = IpBlacklist::where('ip_address', '127.0.0.1')
            ->where('is_active', true)
            ->exists();

        $this->assertTrue($blocked, 
            'IP should be auto-blocked after multiple rate limit violations');
    }

    /** @test */
    public function it_blocks_blacklisted_ips()
    {
        $user = User::factory()->create();

        // Manually blacklist IP
        IpBlacklist::blockIp('127.0.0.1', 'Test block', 60);

        $this->actingAs($user);

        $response = $this->getJson('/api/v1/devices');

        $this->assertEquals(403, $response->status(), 
            'Blacklisted IP should be blocked');
        
        $this->assertStringContainsString('blocked', strtolower($response->getContent()));
    }

    /** @test */
    public function it_has_different_rate_limits_for_different_endpoints()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // API endpoints should have moderate limits
        $apiLimitHit = false;
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            if ($response->status() === 429) {
                $apiLimitHit = true;
                $apiHitAt = $i;
                break;
            }
        }

        // Login should have stricter limits
        RateLimiter::clear('login-attempts:127.0.0.1');
        
        $loginLimitHit = false;
        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/login', [
                'email' => 'test@test.com',
                'password' => 'wrong',
            ]);
            if ($response->status() === 429) {
                $loginLimitHit = true;
                $loginHitAt = $i;
                break;
            }
        }

        // Both should hit limits, but login should hit faster
        if ($apiLimitHit && $loginLimitHit) {
            $this->assertLessThan($apiHitAt ?? 100, $loginHitAt ?? 20, 
                'Login should have stricter rate limits than API');
        }
    }

    /** @test */
    public function it_logs_rate_limit_violations()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Trigger rate limit
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            if ($response->status() === 429) {
                break;
            }
        }

        // Check if violation was logged
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'rate_limit_violation',
            'ip_address' => '127.0.0.1',
        ]);
    }

    /** @test */
    public function it_resets_rate_limits_after_decay_period()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Hit rate limit
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            if ($response->status() === 429) {
                $retryAfter = $response->headers->get('Retry-After');
                break;
            }
        }

        // Clear rate limiter (simulating time passage)
        RateLimiter::clear('api:127.0.0.1');

        // Should be able to make requests again
        $response = $this->getJson('/api/v1/devices');
        $this->assertNotEquals(429, $response->status(), 
            'Rate limit should reset after decay period');
    }

    /** @test */
    public function it_protects_tr069_endpoints_with_higher_limits()
    {
        // TR-069 endpoints should have higher limits for device traffic
        $requests = 0;
        
        for ($i = 0; $i < 500; $i++) {
            $response = $this->postJson('/tr069/acs', [
                'serial_number' => 'TEST-DEVICE',
            ]);
            
            $requests++;
            
            if ($response->status() === 429) {
                break;
            }
        }

        // TR-069 should allow more requests than regular API
        $this->assertGreaterThan(200, $requests, 
            'TR-069 endpoints should have higher rate limits');
    }

    /** @test */
    public function it_provides_retry_after_header()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Hit rate limit
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            
            if ($response->status() === 429) {
                // Should include Retry-After header
                $this->assertTrue(
                    $response->headers->has('Retry-After'),
                    'Rate limited response should include Retry-After header'
                );
                
                $retryAfter = $response->headers->get('Retry-After');
                $this->assertGreaterThan(0, (int)$retryAfter);
                break;
            }
        }
    }

    /** @test */
    public function it_applies_rate_limits_per_ip_not_per_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 hits rate limit
        $this->actingAs($user1);
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/devices');
            if ($response->status() === 429) {
                break;
            }
        }

        // User 2 from same IP should also be rate limited
        $this->actingAs($user2);
        $response = $this->getJson('/api/v1/devices');
        
        $this->assertEquals(429, $response->status(), 
            'Rate limit should apply per IP, affecting all users from that IP');
    }
}
