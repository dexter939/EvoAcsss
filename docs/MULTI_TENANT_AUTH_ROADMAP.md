# Multi-Tenant Authentication Roadmap

## Carrier-Grade ACS Multi-Tenancy Implementation Plan

**Version**: 2.0  
**Date**: December 2025  
**Status**: Phase 0-2 IMPLEMENTED

---

## Executive Summary

This document outlines the phased implementation plan for multi-tenant authentication in the ACS (Auto Configuration Server) platform. The goal is to enable complete tenant isolation while maintaining carrier-grade security and performance for managing 100,000+ CPE devices across multiple customers.

---

## Current Architecture

### Existing Components
- Laravel 11 with Sanctum token authentication
- User model with `tenant_id` field
- CpeDevice model with device access control via `user_devices` pivot table
- Role-based access control (RBAC) with Spatie permissions
- Real-time WebSocket broadcasting with Laravel Reverb
- API authentication for mobile apps (Sanctum tokens)
- Server-to-server API key authentication

---

## Phase 0: Foundation & Inventory (Week 1-2)

### Objectives
- Audit existing tenant-related data
- Seed tenant metadata
- Prepare migration infrastructure

### Tasks

#### 0.1 Tenant Table Enhancement
```sql
CREATE TABLE tenants (
    id SERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    domain VARCHAR(255),
    subdomain VARCHAR(100),
    settings JSONB DEFAULT '{}',
    api_key VARCHAR(64) UNIQUE,
    api_secret VARCHAR(128),
    is_active BOOLEAN DEFAULT true,
    max_devices INTEGER DEFAULT 10000,
    max_users INTEGER DEFAULT 100,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 0.2 Tenant Credentials Table
```sql
CREATE TABLE tenant_credentials (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id),
    credential_type VARCHAR(50), -- 'api_key', 'hmac_secret', 'oauth_client'
    credential_key VARCHAR(255),
    credential_secret TEXT,
    scopes JSONB DEFAULT '[]',
    expires_at TIMESTAMP,
    last_used_at TIMESTAMP,
    created_at TIMESTAMP
);
```

#### 0.3 Extend Existing Tables
```sql
-- Add tenant_id to personal_access_tokens
ALTER TABLE personal_access_tokens ADD COLUMN tenant_id INTEGER REFERENCES tenants(id);
ALTER TABLE personal_access_tokens ADD COLUMN tenant_abilities JSONB DEFAULT '[]';

-- Add tenant_id to sessions
ALTER TABLE sessions ADD COLUMN tenant_id INTEGER;

-- Ensure audit_logs has tenant_id
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS tenant_id INTEGER;
```

### Deliverables
- [x] Database migrations created (tenants, tenant_credentials tables)
- [x] Tenant seeder for existing data
- [x] Audit of current tenant_id usage (cpe_devices, alarms, users)

**Status: IMPLEMENTED (December 2025)**

---

## Phase 1: Dual-Write Mode (Week 3-4)

### Objectives
- Enable tenant context without breaking existing functionality
- Implement read-fallback for backward compatibility

### Tasks

#### 1.1 Tenant Discovery Service
```php
// app/Services/TenantDiscoveryService.php
class TenantDiscoveryService
{
    public function resolve(Request $request): ?Tenant
    {
        // Priority order:
        // 1. Subdomain (customer1.acs.example.com)
        // 2. X-Tenant-ID header (API calls)
        // 3. Token tenant_id claim (Sanctum)
        // 4. User's default tenant
        // 5. Fallback to system tenant
    }
}
```

#### 1.2 Tenant Context Container
```php
// app/Contexts/TenantContext.php
class TenantContext
{
    private static ?Tenant $current = null;
    
    public static function set(Tenant $tenant): void;
    public static function get(): ?Tenant;
    public static function id(): ?int;
    public static function check(): bool;
}
```

#### 1.3 Dual-Write Implementation
- Write `tenant_id` to all new sessions/tokens
- Read with fallback: check tenant_id, fallback to user's tenant if null
- Log all fallback occurrences for monitoring

### Deliverables
- [x] TenantDiscoveryService implemented (app/Services/TenantDiscoveryService.php)
- [x] TenantContext singleton (app/Contexts/TenantContext.php)
- [x] Dual-write enabled on tokens/sessions (HasTenant trait)
- [ ] Monitoring dashboard for fallback occurrences

**Status: IMPLEMENTED (December 2025)**

---

## Phase 2: Middleware Activation (Week 5-6)

### Objectives
- Activate tenant-aware middleware chain
- Integrate with existing EnsureDeviceAccess middleware

### Tasks

#### 2.1 Middleware Stack
```php
// Middleware execution order:
// 1. IdentifyTenant - Resolve tenant from request
// 2. EnforceTenantContext - Set global tenant context
// 3. EnsureTenantPermission - Verify user belongs to tenant
// 4. EnsureDeviceAccess - Existing device access control
```

#### 2.2 IdentifyTenant Middleware
```php
// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = app(TenantDiscoveryService::class)->resolve($request);
        
        if (!$tenant && !$this->isPublicRoute($request)) {
            abort(403, 'Tenant not identified');
        }
        
        TenantContext::set($tenant);
        $request->attributes->set('tenant', $tenant);
        
        return $next($request);
    }
}
```

#### 2.3 EnforceTenantContext Middleware
```php
// app/Http/Middleware/EnforceTenantContext.php
class EnforceTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        
        if ($user && $tenant && $user->tenant_id !== $tenant->id) {
            // Cross-tenant access attempt
            AuditLog::securityEvent('cross_tenant_access_attempt', [
                'user_id' => $user->id,
                'user_tenant' => $user->tenant_id,
                'request_tenant' => $tenant->id,
            ]);
            abort(403, 'Cross-tenant access denied');
        }
        
        return $next($request);
    }
}
```

#### 2.4 Global Scopes for Tenant Isolation
```php
// app/Scopes/TenantScope.php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::check()) {
            $builder->where($model->getTable() . '.tenant_id', TenantContext::id());
        }
    }
}
```

#### 2.5 Feature Flags
```php
// config/features.php
return [
    'multi_tenant' => [
        'enabled' => env('FEATURE_MULTI_TENANT', false),
        'enforce_isolation' => env('FEATURE_TENANT_ISOLATION', false),
        'subdomain_routing' => env('FEATURE_TENANT_SUBDOMAIN', false),
    ],
];
```

### Deliverables
- [x] All middleware implemented (IdentifyTenant, EnforceTenantContext)
- [x] Global scopes applied to tenant-aware models (TenantScope, HasTenant trait)
- [x] Feature flags for gradual rollout (config/tenant.php)
- [x] Middleware applied to ACS web routes and API v1 routes
- [ ] Integration tests passing

**Status: IMPLEMENTED (December 2025)**

---

## Phase 3: Full Isolation Enforcement (Week 7-8)

### Objectives
- Enforce complete tenant isolation
- Remove legacy fallback paths
- Production hardening

### Tasks

#### 3.1 Sanctum Token Scoping
```php
// Extend Sanctum token creation
class TenantAwareTokenService
{
    public function createToken(User $user, string $name, array $abilities = ['*']): NewAccessToken
    {
        $token = $user->createToken($name, $abilities);
        
        // Bind token to tenant
        $token->accessToken->update([
            'tenant_id' => $user->tenant_id,
            'tenant_abilities' => $this->getTenantAbilities($user),
        ]);
        
        return $token;
    }
}
```

#### 3.2 Session Management per Tenant
```php
// config/session.php - Dynamic session domain
'domain' => env('SESSION_DOMAIN', function () {
    if (TenantContext::check()) {
        return TenantContext::get()->subdomain . '.' . config('app.domain');
    }
    return null;
}),
```

#### 3.3 Cache Namespacing
```php
// Cache key prefix per tenant
Cache::tags(['tenant:' . TenantContext::id()])->remember($key, $ttl, $callback);
```

#### 3.4 Queue/Horizon Tenant Tags
```php
// Jobs dispatched with tenant context
class TenantAwareJob implements ShouldQueue
{
    public int $tenantId;
    
    public function __construct()
    {
        $this->tenantId = TenantContext::id();
    }
    
    public function handle(): void
    {
        TenantContext::set(Tenant::find($this->tenantId));
        // Job logic...
    }
}
```

#### 3.5 WebSocket Broadcasting Tenant Isolation
```php
// channels.php - Tenant-scoped private channels
Broadcast::channel('tenant.{tenantId}.alarms', function (User $user, int $tenantId) {
    return $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.devices.{deviceId}', function (User $user, int $tenantId, string $deviceId) {
    return $user->tenant_id === $tenantId && $user->hasDeviceAccess($deviceId);
});
```

### Deliverables
- [x] Token scoping enforced (TenantAwareTokenService, ValidateTokenTenant middleware)
- [x] Session isolation complete (TenantDatabaseSessionHandler, ValidateSessionTenant middleware)
- [x] Cache namespacing active (CacheService with tenant-prefixed keys)
- [ ] Queue tenant tags working
- [ ] WebSocket channels tenant-aware
- [ ] Legacy fallback paths removed

**Token Scoping Status: IMPLEMENTED (December 2025)**
- AuthController uses TenantAwareTokenService for login/refresh
- ValidateTokenTenant middleware validates token-tenant alignment
- Cross-tenant token access logged as critical security events
- Token-user tenant mismatch detection and logging

**Session Isolation Status: IMPLEMENTED (December 2025)**
- TenantDatabaseSessionHandler extends DatabaseSessionHandler with tenant validation
- Dual-layer protection: session handler level + middleware level validation
- Sessions table has tenant_id column (migration: 2025_12_25_115231)
- ValidateSessionTenant middleware validates session tenant after authentication
- Cross-tenant session mismatch triggers logout + session invalidation
- Feature flags for gradual rollout:
  - TENANT_ENABLED=true: Enable tenant context
  - TENANT_ENFORCE_ISOLATION=true: Enforce tenant validation
  - TENANT_REQUIRE_SESSION_TENANT=true: Reject null-tenant sessions
- New session driver: SESSION_DRIVER=tenant_database
- Security logging for cross-tenant access attempts to dedicated security channel

**Cache Namespacing Status: IMPLEMENTED (December 2025)**
- CacheService auto-prefixes all keys with "tenant:{id}:" when tenant.enabled=true
- All cache methods updated: device, profile, statistics, topology, session, rate limiting
- invalidateTenantCache() and invalidateTenantCacheById() for bulk tenant cache clearing
- Backward compatible: empty prefix when tenant features disabled

---

## Phase 4: Security Hardening (Week 9-10)

### Objectives
- Implement carrier-grade security measures
- Compliance with BBF.369 audit requirements

### Tasks

#### 4.1 mTLS for Device-Backend Traffic
```nginx
# Nginx configuration for mTLS
ssl_client_certificate /etc/nginx/certs/tenant-ca-bundle.crt;
ssl_verify_client optional;

location /api/v1/devices {
    if ($ssl_client_verify != SUCCESS) {
        return 403;
    }
}
```

#### 4.2 Tenant Secret Rotation
```php
// Scheduled command for secret rotation
class RotateTenantSecrets extends Command
{
    public function handle(): void
    {
        Tenant::where('api_secret_rotated_at', '<', now()->subDays(90))
            ->each(function (Tenant $tenant) {
                $oldSecret = $tenant->api_secret;
                $tenant->update([
                    'api_secret' => Str::random(128),
                    'api_secret_rotated_at' => now(),
                ]);
                // Keep old secret valid for 24h grace period
                Cache::put("tenant:{$tenant->id}:old_secret", $oldSecret, 86400);
            });
    }
}
```

#### 4.3 Anomaly Detection
```php
// Detect cross-tenant token reuse attempts
class TokenAnomalyDetector
{
    public function check(PersonalAccessToken $token, Request $request): void
    {
        $requestTenantId = $request->header('X-Tenant-ID');
        
        if ($token->tenant_id && $requestTenantId && $token->tenant_id != $requestTenantId) {
            SecurityAlert::dispatch('cross_tenant_token_reuse', [
                'token_id' => $token->id,
                'token_tenant' => $token->tenant_id,
                'request_tenant' => $requestTenantId,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Token tenant mismatch');
        }
    }
}
```

#### 4.4 WAF Rules for Tenant Hosts
```yaml
# WAF rule example (AWS WAF / Cloudflare)
rules:
  - name: "Restrict tenant subdomains"
    condition:
      - field: "host"
        operator: "regex"
        value: "^[a-z0-9-]+\\.acs\\.example\\.com$"
    action: "allow"
  - name: "Block invalid tenant hosts"
    condition:
      - field: "host"
        operator: "not_regex"
        value: "^[a-z0-9-]+\\.acs\\.example\\.com$"
    action: "block"
```

#### 4.5 Comprehensive Audit Logging
```php
// All security events logged with tenant context
AuditLog::create([
    'tenant_id' => TenantContext::id(),
    'user_id' => auth()->id(),
    'action' => $action,
    'resource_type' => $resourceType,
    'resource_id' => $resourceId,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'metadata' => $metadata,
    'created_at' => now(),
]);
```

### Deliverables
- [x] mTLS configuration documented (docs/SECURITY_HARDENING.md)
- [x] Secret rotation automated (RotateTenantSecrets command, weekly schedule)
- [x] Anomaly detection active (TenantAnomalyDetector service)
- [x] WAF rules documented (docs/SECURITY_HARDENING.md)
- [x] Audit logging complete (security_alerts table, SecurityAlertService)
- [ ] BBF.369 compliance verified

**Security Hardening Status: IMPLEMENTED (December 2025)**
- RotateTenantSecrets command with grace period support
- Weekly scheduled rotation (Sundays 4:00 AM)
- TenantAnomalyDetector service for real-time threat detection
- SecurityAlertService for multi-channel alerting (log, database, email, webhook)
- security_alerts table for persistent alert storage
- Integration with ValidateTokenTenant middleware
- Comprehensive documentation in docs/SECURITY_HARDENING.md

---

## API Authentication Matrix

| Client Type | Auth Method | Tenant Resolution | Token Scope |
|-------------|-------------|-------------------|-------------|
| Mobile App | Sanctum SPA Token | X-Tenant-ID header | User's tenant abilities |
| Web Dashboard | Session + CSRF | Subdomain | Full tenant access |
| Server-to-Server | HMAC Signed Request | API Key lookup | Tenant credentials |
| CPE Device | mTLS + Device Cert | Certificate CN | Device-level only |
| WebSocket | Sanctum Token | Token tenant_id | Real-time channels |

---

## Environment Variables

```env
# Multi-Tenant Feature Flags
FEATURE_MULTI_TENANT=true
FEATURE_TENANT_ISOLATION=true
FEATURE_TENANT_SUBDOMAIN=true

# Tenant Discovery
TENANT_DISCOVERY_METHOD=subdomain
TENANT_DEFAULT_ID=1
TENANT_HEADER_NAME=X-Tenant-ID

# Session per Tenant
SESSION_DOMAIN=.acs.example.com
SESSION_TENANT_AWARE=true

# Security
TENANT_SECRET_ROTATION_DAYS=90
TENANT_TOKEN_ANOMALY_DETECTION=true
```

---

## Migration Checklist

### Pre-Migration
- [ ] Backup all databases
- [ ] Document current tenant_id assignments
- [ ] Create rollback plan
- [ ] Notify affected customers

### During Migration
- [ ] Run migrations in maintenance mode
- [ ] Seed tenant table from existing data
- [ ] Backfill tenant_id on tokens/sessions
- [ ] Enable dual-write mode

### Post-Migration
- [ ] Monitor fallback occurrences
- [ ] Verify no cross-tenant data leakage
- [ ] Test all API endpoints per tenant
- [ ] Validate WebSocket isolation
- [ ] Run TR-069/369 transport tests
- [ ] Test mobile app authentication

---

## Testing Requirements

### Unit Tests
- Tenant discovery from various sources
- Middleware isolation enforcement
- Token tenant binding
- Global scope filtering

### Integration Tests
- Cross-tenant access denial
- Session isolation per subdomain
- WebSocket channel authorization
- API authentication flows

### Security Tests
- Cross-tenant token reuse detection
- Privilege escalation attempts
- SQL injection with tenant context
- Session fixation attacks

### Performance Tests
- Query performance with tenant scopes
- Cache hit rates per tenant
- WebSocket connection limits per tenant

---

## Timeline Summary

| Phase | Duration | Key Milestone |
|-------|----------|---------------|
| Phase 0 | Week 1-2 | Database schema ready |
| Phase 1 | Week 3-4 | Dual-write enabled |
| Phase 2 | Week 5-6 | Middleware active |
| Phase 3 | Week 7-8 | Full isolation enforced |
| Phase 4 | Week 9-10 | Security hardened |

**Total Duration**: 10 weeks

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Data leakage during migration | Dual-write mode with monitoring |
| Performance degradation | Indexed tenant_id, query optimization |
| Existing API breakage | Feature flags for gradual rollout |
| Token invalidation | Grace period for old tokens |
| Customer disruption | Staged rollout by tenant |

---

## Success Metrics

- Zero cross-tenant data access incidents
- < 5ms overhead per request for tenant resolution
- 100% audit log coverage for security events
- All TR protocols working with tenant isolation
- Mobile app seamless tenant switching
- WebSocket channels properly scoped

---

## Next Steps

1. Review this roadmap with stakeholders
2. Prioritize phases based on business needs
3. Create detailed technical design documents
4. Begin Phase 0 database migrations
5. Set up monitoring dashboards
