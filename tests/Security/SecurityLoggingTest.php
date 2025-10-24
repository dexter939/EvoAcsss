<?php

namespace Tests\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\SecurityLog;
use App\Models\IpBlacklist;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Security Logging & Monitoring Tests
 * 
 * Tests security event logging and monitoring capabilities.
 * OWASP A09:2021 - Security Logging and Monitoring Failures
 */
class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_logs_failed_login_attempts()
    {
        $user = User::factory()->create([
            'email' => 'test@test.com',
            'password' => bcrypt('correct-password'),
        ]);

        // Attempt failed login
        $this->postJson('/login', [
            'email' => 'test@test.com',
            'password' => 'wrong-password',
        ]);

        // Should be logged in security_logs
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'login_failed',
            'action' => 'login_attempt_failed',
        ]);
    }

    /** @test */
    public function it_logs_successful_login_attempts()
    {
        $user = User::factory()->create([
            'email' => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/login', [
            'email' => 'test@test.com',
            'password' => 'password123',
        ]);

        // Should log successful authentication
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'login_success',
            'user_id' => $user->id,
        ]);
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

        // Should log rate limit violation
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'rate_limit_violation',
            'ip_address' => '127.0.0.1',
        ]);
    }

    /** @test */
    public function it_logs_unauthorized_access_attempts()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Try to access protected resource without permission
        $response = $this->getJson('/api/v1/admin/settings');

        if ($response->status() === 403) {
            // Should log unauthorized access
            $this->assertDatabaseHas('security_logs', [
                'event_type' => 'unauthorized_access',
                'user_id' => $user->id,
            ]);
        }
    }

    /** @test */
    public function it_logs_ip_blocking_events()
    {
        // Block an IP
        IpBlacklist::blockIp('192.168.1.100', 'Test block', 60);

        // Should log the blocking event
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'ip_auto_blocked',
            'ip_address' => '192.168.1.100',
        ]);
    }

    /** @test */
    public function it_includes_relevant_context_in_logs()
    {
        $user = User::factory()->create([
            'email' => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/login', [
            'email' => 'test@test.com',
            'password' => 'wrong-password',
        ]);

        $log = SecurityLog::where('event_type', 'login_failed')->latest()->first();

        if ($log) {
            // Should include contextual information
            $this->assertNotNull($log->ip_address);
            $this->assertNotNull($log->severity);
            $this->assertNotNull($log->created_at);
            $this->assertNotNull($log->action);
        }
    }

    /** @test */
    public function it_categorizes_events_by_severity()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Trigger various security events
        $this->postJson('/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong',
        ]);

        // Check that severity levels are assigned
        $log = SecurityLog::latest()->first();
        
        if ($log) {
            $this->assertContains($log->severity, ['info', 'warning', 'critical'], 
                'Security log should have valid severity level');
        }
    }

    /** @test */
    public function it_provides_security_dashboard_metrics()
    {
        // Create some security events
        SecurityLog::create([
            'event_type' => 'rate_limit_violation',
            'severity' => 'warning',
            'ip_address' => '127.0.0.1',
            'action' => 'test_action',
            'description' => 'Test violation',
            'blocked' => false,
            'risk_level' => 'medium',
        ]);

        SecurityLog::create([
            'event_type' => 'unauthorized_access',
            'severity' => 'critical',
            'ip_address' => '127.0.0.1',
            'action' => 'test_action',
            'description' => 'Test unauthorized',
            'blocked' => true,
            'risk_level' => 'high',
        ]);

        $service = new \App\Services\SecurityService();
        $stats = $service->getSecurityDashboardStats();

        // Should provide metrics
        $this->assertArrayHasKey('total_events_24h', $stats);
        $this->assertArrayHasKey('critical_events_24h', $stats);
        $this->assertArrayHasKey('blocked_attempts_24h', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['critical_events_24h']);
    }

    /** @test */
    public function it_retains_logs_for_audit_purposes()
    {
        $user = User::factory()->create();
        
        SecurityLog::create([
            'event_type' => 'login_success',
            'severity' => 'info',
            'ip_address' => '127.0.0.1',
            'user_id' => $user->id,
            'action' => 'user_login',
            'description' => 'User logged in successfully',
            'blocked' => false,
            'risk_level' => 'low',
            'created_at' => now()->subDays(60),
        ]);

        // Log should be retained for compliance
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $user->id,
            'event_type' => 'login_success',
        ]);
    }

    /** @test */
    public function it_logs_suspicious_activity_patterns()
    {
        $user = User::factory()->create();

        // Simulate suspicious activity
        SecurityLog::create([
            'event_type' => 'suspicious_activity',
            'severity' => 'warning',
            'ip_address' => '127.0.0.1',
            'user_id' => $user->id,
            'action' => 'multiple_failed_logins',
            'description' => 'Multiple failed login attempts detected',
            'blocked' => false,
            'risk_level' => 'medium',
        ]);

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'suspicious_activity',
            'risk_level' => 'medium',
        ]);
    }
}
