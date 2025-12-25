# Legacy Fallback Removal Guide

## Overview

This document describes the changes made to remove backward compatibility code and enforce full multi-tenant isolation in production.

## Changes Made (December 2025)

### 1. Configuration Defaults Updated

**File:** `config/tenant.php`

| Setting | Before | After |
|---------|--------|-------|
| `enabled` | `false` | `true` |
| `enforce_isolation` | `false` | `true` |
| `require_tenant` | `false` | `true` |
| `require_session_tenant` | `false` | `true` |

### 2. Database Migration

**File:** `database/migrations/2025_12_25_200000_enforce_tenant_id_not_null.php`

This migration:
- Creates default tenant (ID=1) if not exists
- Assigns default tenant to any orphaned records (one-time data fix)
- Makes `tenant_id` NOT NULL (without default) on: users, cpe_devices, alarms, sessions, personal_access_tokens
- **Important**: No column default is set, ensuring all new records fail if tenant_id is not explicitly provided

### 3. Code Cleanup

**AlarmCreated Event:**
- Removed user-specific channel broadcasting (backward compatibility)
- Removed legacy `message` field mapping
- Now broadcasts exclusively to tenant channels

**ValidateTokenTenant Middleware:**
- Removed feature flag checks (always enforced)
- Simplified tenant validation logic

## Migration Steps

### Before Running Migration

1. **Verify all records have tenant_id:**
```sql
SELECT COUNT(*) FROM users WHERE tenant_id IS NULL;
SELECT COUNT(*) FROM cpe_devices WHERE tenant_id IS NULL;
SELECT COUNT(*) FROM alarms WHERE tenant_id IS NULL;
```

2. **Ensure default tenant exists:**
```sql
SELECT * FROM tenants WHERE id = 1;
```

### Running the Migration

```bash
php artisan migrate
```

### Rollback (if needed)

```bash
php artisan migrate:rollback --step=1
```

This will restore nullable tenant_id columns.

## Environment Variables

Ensure production environment has:

```env
TENANT_ENABLED=true
TENANT_ENFORCE_ISOLATION=true
TENANT_REQUIRE_TENANT=true
TENANT_REQUIRE_SESSION_TENANT=true
```

## Breaking Changes

1. **API clients** must include tenant context (via subdomain, header, or token)
2. **WebSocket clients** must subscribe to `tenant.{id}` channels instead of `user.{id}`
3. **All new records** require explicit `tenant_id` assignment

## Monitoring

After deployment, monitor for:
- Security alerts for cross-tenant access attempts
- Failed authentication due to missing tenant context
- WebSocket connection failures

## Support

For issues, check:
- `storage/logs/security.log` for tenant violations
- `security_alerts` table for anomaly detections
