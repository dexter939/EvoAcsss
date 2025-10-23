# Security Documentation - ACS System

## Table of Contents

1. [Overview](#overview)
2. [Audit Log System](#audit-log-system)
3. [Security Logging](#security-logging)
4. [Authentication & Authorization](#authentication--authorization)
5. [API Security](#api-security)
6. [Multi-Tenant Security](#multi-tenant-security)
7. [Compliance & Retention](#compliance--retention)

---

## Overview

The ACS (Auto Configuration Server) system implements carrier-grade security controls for managing 100,000+ CPE devices. This document covers security architecture, audit logging, compliance tracking, and operational security procedures.

---

## Audit Log System

### Purpose

The Audit Log System provides comprehensive compliance and security tracking for all CRUD operations and business-critical actions across the ACS platform. Designed for carrier-grade environments, it supports regulatory compliance (SOC 2, ISO 27001, HIPAA) and forensic analysis.

### Architecture

**Database Schema** (`audit_logs` table):
- Polymorphic relations (`auditable_type`, `auditable_id`) for tracking any model
- Change tracking (`old_values`, `new_values` JSON columns)
- Contextual metadata (IP address, user agent, URL, HTTP method)
- Categorization (event type, category, severity, compliance critical flag)
- Tagging system for flexible filtering
- Performance indexes for 100K+ scale operations

**Core Components**:

1. **AuditLog Model** (`app/Models/AuditLog.php`)
   - Eloquent model with polymorphic relations
   - Powerful query scopes for filtering
   - Automatic JSON casting for metadata

2. **AuditLogger Service** (`app/Services/AuditLogger.php`)
   - Centralized logging facade
   - Queue support for high-volume logging
   - Singleton pattern for performance

3. **Auditable Trait** (`app/Traits/Auditable.php`)
   - Auto-tracks model created/updated/deleted events
   - Configurable per-model categories
   - Field exclusion support (e.g., passwords)

4. **RecordAuditLog Middleware** (`app/Http/Middleware/RecordAuditLog.php`)
   - Captures all authenticated HTTP requests
   - Routes audit logs to appropriate categories
   - Minimal performance overhead

5. **API Endpoints** (`/api/v1/audit-logs/*`)
   - RESTful access to audit logs
   - Advanced filtering and search
   - CSV/JSON export for compliance reporting
   - Statistical summaries

6. **Cleanup Command** (`php artisan audit:cleanup`)
   - Automated retention policy enforcement
   - Configurable retention periods
   - Compliance-critical log preservation

### Usage

#### Automatic Tracking (Models)

Apply the `Auditable` trait to any Eloquent model:

```php
use App\Traits\Auditable;

class CpeDevice extends Model
{
    use Auditable;
    
    protected $auditCategory = 'device';
    protected $auditExcludeFields = ['password', 'secret_key'];
}
```

**Tracked Events**:
- `created` - When a new record is created
- `updated` - When a record is modified (captures old/new values)
- `deleted` - When a record is soft/hard deleted

#### Manual Logging

For business-critical actions not tied to models:

```php
use App\Services\AuditLogger;

// Simple log
AuditLogger::log(
    event: 'firmware_upgrade_initiated',
    description: 'Firmware upgrade started for device CPE-12345',
    severity: 'high',
    category: 'firmware'
);

// With context
AuditLogger::log(
    event: 'bulk_provisioning_completed',
    description: '500 devices provisioned successfully',
    severity: 'medium',
    category: 'provisioning',
    metadata: [
        'device_count' => 500,
        'profile_id' => 42,
        'duration_seconds' => 125.3
    ],
    tags: ['bulk', 'production', 'success']
);

// Critical compliance events
AuditLogger::log(
    event: 'user_permission_escalation',
    description: 'User admin@example.com granted super-admin role',
    severity: 'critical',
    category: 'security',
    complianceCritical: true,  // Never auto-deleted
    metadata: ['target_user_id' => 15, 'previous_role' => 'manager']
);
```

#### Queued Logging (High Volume)

For high-throughput scenarios, queue audit logs to avoid blocking:

```php
AuditLogger::logQueued(
    event: 'device_inform_received',
    description: "Inform from device {$device->serial_number}",
    category: 'tr069',
    metadata: ['device_id' => $device->id]
);
```

#### API Access

**List audit logs with filtering**:
```http
GET /api/v1/audit-logs?user_id=42&category=device&from=2025-01-01&to=2025-12-31&per_page=50
```

**Query parameters**:
- `user_id` - Filter by user
- `event` - Filter by event type
- `category` - Filter by category (device, user, firmware, etc.)
- `severity` - Filter by severity (low, medium, high, critical)
- `from`, `to` - Date range
- `model_type`, `model_id` - Filter by specific model
- `search` - Full-text search in descriptions
- `compliance_critical` - Show only critical logs
- `order` - `asc` or `desc` (default: `desc`)
- `per_page` - Results per page (max 100)

**Get single audit log**:
```http
GET /api/v1/audit-logs/{id}
```

**Get logs for specific model**:
```http
GET /api/v1/audit-logs/for-model?model_type=App\Models\CpeDevice&model_id=123
```

**Export to CSV**:
```http
GET /api/v1/audit-logs/export/csv?category=device&from=2025-01-01
```

**Export to JSON**:
```http
GET /api/v1/audit-logs/export/json?compliance_critical=true
```

**Get statistics**:
```http
GET /api/v1/audit-logs/statistics?from=2025-01-01&to=2025-12-31
```

Response includes:
- Total log count
- Breakdown by event type
- Breakdown by category
- Breakdown by severity
- Top 10 active users
- Compliance-critical event count

### Retention Policy

**Default Retention**: 90 days for standard logs

**Compliance-Critical Logs**: Never auto-deleted (must be manually archived)

**Cleanup Command**:
```bash
# Dry run (preview only)
php artisan audit:cleanup --days=90 --dry-run

# Delete logs older than 90 days, keep compliance-critical
php artisan audit:cleanup --days=90 --keep-critical

# Delete all logs older than 180 days (including critical)
php artisan audit:cleanup --days=180

# Schedule in cron (recommended)
0 2 * * * cd /path/to/acs && php artisan audit:cleanup --days=90 --keep-critical
```

**Scheduler Integration** (add to `app/Console/Kernel.php`):
```php
protected function schedule(Schedule $schedule)
{
    // Run cleanup daily at 2 AM
    $schedule->command('audit:cleanup --days=90 --keep-critical')
             ->dailyAt('02:00')
             ->appendOutputTo(storage_path('logs/audit-cleanup.log'));
}
```

### Event Categories

| Category | Description | Examples |
|----------|-------------|----------|
| `device` | CPE device operations | Device created, updated, deleted, provisioned |
| `user` | User account management | User created, role changed, password reset |
| `configuration` | Configuration profiles | Profile applied, template modified |
| `firmware` | Firmware management | Upgrade initiated, firmware uploaded |
| `security` | Security-related events | Failed login, permission denied |
| `tr069` | TR-069 protocol events | Connection request, RPC executed |
| `tr369` | TR-369 USP events | USP message sent, subscription created |
| `provisioning` | Provisioning operations | Bulk provisioning, parameter set |
| `diagnostics` | Diagnostics tests | Ping test, traceroute executed |
| `system` | System administration | System update, backup created |

### Severity Levels

| Severity | Usage | Retention |
|----------|-------|-----------|
| `low` | Informational events | 30 days |
| `medium` | Normal operations | 90 days |
| `high` | Important changes | 180 days |
| `critical` | Security/compliance events | Permanent (if `compliance_critical=true`) |

### Compliance-Critical Events

Mark events that must never be auto-deleted for regulatory compliance:

```php
AuditLogger::log(
    event: 'gdpr_data_deletion_request',
    description: 'User requested GDPR data deletion',
    severity: 'critical',
    category: 'compliance',
    complianceCritical: true  // Never deleted
);
```

**Examples**:
- User permission escalations
- Data deletion requests (GDPR, CCPA)
- Security policy changes
- Audit log access
- Bulk operations affecting >100 devices
- Firmware rollbacks
- System backup/restore operations

### Performance Considerations

**Database Indexes**:
- Composite index on `(user_id, created_at)`
- Index on `category`
- Index on `event`
- Index on `auditable_type, auditable_id`
- Index on `compliance_critical`

**Optimization Tips**:
1. Use queued logging (`logQueued`) for high-volume events
2. Enable database query caching for read-heavy workloads
3. Archive old logs to cold storage (S3, Glacier) for long-term retention
4. Use `audit:cleanup` regularly to prevent table bloat
5. Consider partitioning `audit_logs` table by date for >1M records

**Estimated Storage**:
- Average audit log: ~1KB
- 100 logs/second: ~8.6GB/day
- 90-day retention: ~774GB
- Recommendation: Monitor disk usage, implement archival strategy

### Security Best Practices

1. **Access Control**: Restrict `/api/v1/audit-logs/*` endpoints to admin users only
2. **Immutability**: Audit logs should never be updated or deleted (except via retention policy)
3. **Tamper Detection**: Consider implementing log signing/hashing for forensic integrity
4. **Encryption**: Encrypt audit logs at rest if they contain sensitive data
5. **Monitoring**: Alert on suspicious patterns (bulk deletions, permission escalations)
6. **Backup**: Include audit logs in disaster recovery backups

---

## Security Logging

### SecurityLog vs AuditLog

**SecurityLog** (`security_logs` table):
- **Purpose**: Security event tracking (threats, attacks, anomalies)
- **Focus**: Rate limiting, unauthorized access, brute force, DDoS
- **Usage**: Real-time security monitoring and incident response

**AuditLog** (`audit_logs` table):
- **Purpose**: Compliance and operational tracking
- **Focus**: CRUD operations, business actions, change history
- **Usage**: Regulatory compliance, forensic analysis, audit trails

**When to use SecurityLog**:
```php
SecurityLog::create([
    'event_type' => 'rate_limit_exceeded',
    'severity' => 'warning',
    'ip_address' => request()->ip(),
    'description' => 'API rate limit exceeded (100 req/min)',
    'risk_level' => 'medium',
    'blocked' => true
]);
```

**When to use AuditLog**:
```php
AuditLogger::log(
    event: 'device_configuration_updated',
    description: 'WiFi password changed for device CPE-123',
    category: 'device',
    severity: 'medium'
);
```

---

## Authentication & Authorization

### Role-Based Access Control (RBAC)

**Roles**:
- `super-admin`: Full system access
- `admin`: Customer-level administration
- `manager`: Device management and provisioning
- `viewer`: Read-only access

**Device-Scoped Permissions**:
```php
// Check device access
if (!auth()->user()->hasDeviceAccess($device, 'manager')) {
    abort(403, 'Insufficient device permissions');
}
```

**Middleware Usage**:
```php
// Require viewer access (read)
Route::middleware('device.access')->group(function () {
    Route::get('devices', [DeviceController::class, 'index']);
});

// Require manager access (write)
Route::middleware('device.access:manager')->group(function () {
    Route::post('devices/{device}/provision', [ProvisioningController::class, 'provision']);
});
```

---

## API Security

### API Key Authentication

**Middleware**: `ApiKeyAuth`

**Usage**:
```http
GET /api/v1/devices
Authorization: Bearer your-api-key-here
```

**Rate Limiting**:
- Standard users: 100 requests/minute
- Admin users: 500 requests/minute
- Burst allowance: 2x rate limit for 10 seconds

**DDoS Protection**:
- IP-based rate limiting
- Automatic blacklisting after threshold
- Challenge-response for suspicious patterns

---

## Multi-Tenant Security

### Tenant Isolation

**Database-Level**:
- User-device associations via `user_devices` pivot table
- Automatic scoping via `device.access` middleware
- Super-admin bypass for cross-tenant operations

**Data Leakage Prevention**:
```php
// Automatic tenant filtering
$devices = auth()->user()->accessibleDevices()->get();

// Manual check
if (!auth()->user()->canAccessDevice($deviceId)) {
    abort(403);
}
```

---

## Compliance & Retention

### Supported Standards

- **SOC 2**: Comprehensive audit trails for all data access
- **ISO 27001**: Security event logging and monitoring
- **HIPAA**: Access control and audit logging (if applicable)
- **GDPR**: Data access/deletion tracking
- **PCI DSS**: Change tracking for sensitive configurations

### Audit Trail Requirements

**What to log**:
- ✅ All CRUD operations on sensitive data
- ✅ Authentication attempts (success/failure)
- ✅ Authorization failures
- ✅ Configuration changes
- ✅ Firmware upgrades
- ✅ Bulk operations
- ✅ Data exports
- ✅ User permission changes

**What NOT to log**:
- ❌ Passwords (plain or hashed)
- ❌ API keys or secrets
- ❌ Excessive read operations (performance impact)
- ❌ Non-business-critical events (e.g., UI interactions)

### Reporting

**Monthly Compliance Report**:
```bash
php artisan audit:cleanup --days=30 --dry-run > monthly_audit_summary.txt
```

**Quarterly Export**:
```http
GET /api/v1/audit-logs/export/csv?compliance_critical=true&from=2025-01-01&to=2025-03-31
```

---

## Incident Response

### Security Incident Workflow

1. **Detection**: Monitor SecurityLog for anomalies
2. **Containment**: Block IP/user via blacklist
3. **Analysis**: Review AuditLog for affected resources
4. **Remediation**: Revoke compromised credentials, restore backups
5. **Documentation**: Export audit logs for post-mortem

### Forensic Analysis

**Investigate user activity**:
```http
GET /api/v1/audit-logs?user_id=42&from=2025-10-20&to=2025-10-23&order=asc
```

**Investigate device changes**:
```http
GET /api/v1/audit-logs/for-model?model_type=App\Models\CpeDevice&model_id=123
```

**Search for specific actions**:
```http
GET /api/v1/audit-logs?search=firmware_upgrade&severity=critical
```

---

## Monitoring & Alerting

### Critical Alerts

**Configure AlertManager for**:
- Excessive failed login attempts (>10/minute)
- Bulk device deletions (>50 at once)
- Permission escalations to super-admin
- Compliance-critical events
- Abnormal API usage patterns

**Prometheus Metrics**:
- `acs_audit_logs_total{category, severity}`
- `acs_security_events_total{event_type, risk_level}`
- `acs_failed_auth_attempts_total`

---

## Troubleshooting

### Common Issues

**Audit logs not appearing**:
1. Check middleware is registered in `bootstrap/app.php`
2. Verify `Auditable` trait is applied to model
3. Check database connection
4. Review `storage/logs/laravel.log` for errors

**Performance degradation**:
1. Enable queued logging: `AuditLogger::logQueued()`
2. Optimize database indexes
3. Run `audit:cleanup` more frequently
4. Archive old logs to cold storage

**Export fails (timeout)**:
1. Reduce date range
2. Use pagination instead of full export
3. Increase PHP `max_execution_time`
4. Export in background job

---

## Appendix

### Database Schema

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT,
    auditable_type VARCHAR(255),
    auditable_id BIGINT,
    event VARCHAR(255) NOT NULL,
    action VARCHAR(255),
    description TEXT,
    old_values JSONB,
    new_values JSONB,
    metadata JSONB,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    url VARCHAR(1000),
    http_method VARCHAR(10),
    category VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    compliance_critical BOOLEAN DEFAULT FALSE,
    tags JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at);
CREATE INDEX idx_audit_category ON audit_logs(category);
CREATE INDEX idx_audit_event ON audit_logs(event);
CREATE INDEX idx_audit_auditable ON audit_logs(auditable_type, auditable_id);
CREATE INDEX idx_audit_compliance ON audit_logs(compliance_critical);
```

### Sample Integration

```php
// In a controller
use App\Services\AuditLogger;

class DeviceController extends Controller
{
    public function provisionDevice(CpeDevice $device, Request $request)
    {
        // ... provisioning logic ...
        
        AuditLogger::log(
            event: 'device_provisioned',
            description: "Device {$device->serial_number} provisioned with profile {$profileId}",
            severity: 'high',
            category: 'provisioning',
            metadata: [
                'device_id' => $device->id,
                'profile_id' => $profileId,
                'parameters_count' => count($parameters)
            ],
            tags: ['provisioning', 'production']
        );
        
        return response()->json(['success' => true]);
    }
}
```

---

**Document Version**: 1.0  
**Last Updated**: October 23, 2025  
**Maintained By**: ACS Security Team
