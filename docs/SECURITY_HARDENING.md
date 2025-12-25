# Security Hardening Guide

## Carrier-Grade ACS Security Implementation

**Version**: 1.0  
**Date**: December 2025  
**Compliance**: BBF.369, SOC 2, ISO 27001

---

## Overview

This document describes the security hardening measures implemented in Phase 4 of the Multi-Tenant ACS system. These measures provide carrier-grade security for managing 100,000+ CPE devices across multiple tenants.

---

## 1. Tenant Secret Rotation

### Automated Rotation Command

```bash
# Manual rotation for all eligible tenants
php artisan tenant:rotate-secrets

# Force rotation for specific tenant
php artisan tenant:rotate-secrets --tenant=1 --force

# Custom rotation interval (default: 90 days)
php artisan tenant:rotate-secrets --days=60 --grace=12
```

### Schedule Configuration

The system automatically rotates secrets weekly (Sundays at 4:00 AM):

```php
// routes/console.php
Schedule::command('tenant:rotate-secrets')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping()
    ->onOneServer();
```

### Grace Period

Old secrets remain valid for a configurable grace period (default: 24 hours) to allow clients to update their credentials without service interruption.

### Environment Variables

```env
TENANT_SECRET_ROTATION_DAYS=90
TENANT_SECRET_GRACE_HOURS=24
```

---

## 2. Anomaly Detection

### TenantAnomalyDetector Service

The `TenantAnomalyDetector` service monitors for security anomalies:

#### Detected Anomalies

| Type | Severity | Description |
|------|----------|-------------|
| `cross_tenant_token_reuse` | Critical | Token used against different tenant |
| `auth_blocked` | Critical | Too many failed auth attempts |
| `rapid_ip_switching` | High | Same token used from many IPs rapidly |
| `failed_auth_attempt` | Medium | Failed authentication |
| `rate_limit_violation` | Medium | Rate limit exceeded |
| `unusual_ip_diversity` | Low | Token accessed from many unique IPs |

#### Configuration

```env
TENANT_ANOMALY_DETECTION=true
TENANT_FAILED_AUTH_THRESHOLD=5
TENANT_RATE_LIMIT_THRESHOLD=10
TENANT_CROSS_TENANT_THRESHOLD=1
TENANT_UNUSUAL_IP_THRESHOLD=10
TENANT_DETECTION_WINDOW_MINUTES=15
```

#### Integration Points

- `ValidateTokenTenant` middleware - Checks token anomalies on every API request
- `AuthController` - Records failed authentication attempts
- `RateLimitMiddleware` - Records rate limit violations

---

## 3. Security Alert System

### SecurityAlertService

Multi-channel alert system for security events:

#### Alert Channels

| Channel | Description |
|---------|-------------|
| `log` | Security log channel (storage/logs/security.log) |
| `database` | Persistent storage in security_alerts table |
| `email` | Email notifications to admins |
| `webhook` | External webhook integration |

#### Configuration

```env
TENANT_ALERT_ON_ANOMALY=true
TENANT_ALERT_CHANNELS=log,database,email
TENANT_ALERT_EMAILS=security@example.com,admin@example.com
TENANT_SECURITY_WEBHOOK_URL=https://security.example.com/webhook
TENANT_SECURITY_WEBHOOK_SECRET=your-webhook-secret
```

#### Alert Management API

```php
// Acknowledge an alert
$alertService->acknowledgeAlert($alertId, $userId);

// Get unacknowledged alerts for tenant
$alerts = $alertService->getUnacknowledgedAlerts($tenantId);

// Get all unacknowledged alerts (system-wide)
$alerts = $alertService->getUnacknowledgedAlerts();
```

---

## 4. mTLS Configuration (Nginx)

### Device-Backend Traffic Encryption

For carrier-grade deployments, configure mTLS for device-to-backend communication:

```nginx
# /etc/nginx/conf.d/acs-mtls.conf

server {
    listen 443 ssl http2;
    server_name api.acs.example.com;

    # Server certificates
    ssl_certificate /etc/nginx/certs/acs-server.crt;
    ssl_certificate_key /etc/nginx/certs/acs-server.key;

    # Client certificate verification
    ssl_client_certificate /etc/nginx/certs/tenant-ca-bundle.crt;
    ssl_verify_client optional;
    ssl_verify_depth 2;

    # TLS 1.3 only for maximum security
    ssl_protocols TLSv1.3;
    ssl_prefer_server_ciphers on;

    # Device API endpoints require client certificate
    location /api/v1/devices {
        if ($ssl_client_verify != SUCCESS) {
            return 403;
        }
        
        # Pass client certificate info to backend
        proxy_set_header X-Client-Cert $ssl_client_cert;
        proxy_set_header X-Client-DN $ssl_client_s_dn;
        proxy_pass http://127.0.0.1:5000;
    }

    # TR-069 endpoint (CPE devices)
    location /tr069 {
        if ($ssl_client_verify != SUCCESS) {
            return 403;
        }
        
        proxy_set_header X-Client-Cert $ssl_client_cert;
        proxy_pass http://127.0.0.1:5000;
    }

    # Web dashboard (standard TLS)
    location / {
        proxy_pass http://127.0.0.1:5000;
    }
}
```

### Certificate Management

```bash
# Generate tenant CA (one per tenant)
openssl genrsa -out tenant-1-ca.key 4096
openssl req -new -x509 -days 3650 -key tenant-1-ca.key \
    -out tenant-1-ca.crt -subj "/CN=Tenant 1 CA/O=ACS"

# Generate device certificate
openssl genrsa -out device-001.key 2048
openssl req -new -key device-001.key -out device-001.csr \
    -subj "/CN=device-001/O=Tenant 1"
openssl x509 -req -in device-001.csr -CA tenant-1-ca.crt \
    -CAkey tenant-1-ca.key -CAcreateserial -out device-001.crt -days 365

# Bundle all tenant CAs
cat tenant-*-ca.crt > tenant-ca-bundle.crt
```

---

## 5. WAF Rules Configuration

### AWS WAF

```yaml
# aws-waf-rules.yaml
WebACL:
  Name: ACS-Security-ACL
  Rules:
    - Name: ValidateTenantSubdomain
      Priority: 1
      Statement:
        RegexPatternSetReferenceStatement:
          FieldToMatch:
            SingleHeader:
              Name: Host
          TextTransformations:
            - Priority: 0
              Type: LOWERCASE
          ARN: !Ref TenantSubdomainPatternSet
      Action:
        Allow: {}
    
    - Name: BlockInvalidHosts
      Priority: 2
      Statement:
        NotStatement:
          Statement:
            RegexPatternSetReferenceStatement:
              FieldToMatch:
                SingleHeader:
                  Name: Host
              ARN: !Ref TenantSubdomainPatternSet
      Action:
        Block: {}
    
    - Name: RateLimitByTenant
      Priority: 3
      Statement:
        RateBasedStatement:
          Limit: 2000
          AggregateKeyType: FORWARDED_IP
      Action:
        Block: {}

RegexPatternSet:
  Name: TenantSubdomainPatternSet
  Patterns:
    - ^[a-z0-9-]+\.acs\.example\.com$
    - ^api\.acs\.example\.com$
    - ^acs\.example\.com$
```

### Cloudflare WAF

```javascript
// Cloudflare Worker for tenant validation
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const url = new URL(request.url)
  const host = url.hostname
  
  // Validate tenant subdomain format
  const validPattern = /^[a-z0-9-]+\.acs\.example\.com$/
  const allowedHosts = ['acs.example.com', 'api.acs.example.com']
  
  if (!validPattern.test(host) && !allowedHosts.includes(host)) {
    return new Response('Invalid host', { status: 403 })
  }
  
  // Extract tenant from subdomain
  const tenantMatch = host.match(/^([a-z0-9-]+)\.acs\.example\.com$/)
  if (tenantMatch) {
    request = new Request(request)
    request.headers.set('X-Tenant-Slug', tenantMatch[1])
  }
  
  return fetch(request)
}
```

---

## 6. Audit Logging

### Comprehensive Audit Trail

All security events are logged with full tenant context:

```php
// Automatic audit logging via RecordAuditLog middleware
AuditLog::create([
    'tenant_id' => TenantContext::id(),
    'user_id' => auth()->id(),
    'action' => 'device.update',
    'resource_type' => 'CpeDevice',
    'resource_id' => $device->id,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'old_values' => $oldValues,
    'new_values' => $newValues,
    'metadata' => ['session_id' => session()->getId()],
]);
```

### Security Log Channel

Dedicated security logging with 90-day retention:

```php
// config/logging.php
'security' => [
    'driver' => 'daily',
    'path' => storage_path('logs/security.log'),
    'level' => 'debug',
    'days' => 90,
    'replace_placeholders' => true,
],
```

---

## 7. Compliance Checklist

### BBF.369 Compliance

- [x] Device authentication via certificates
- [x] Encrypted transport (TLS 1.3)
- [x] Audit logging for all operations
- [x] Role-based access control
- [x] Tenant isolation

### SOC 2 Type II

- [x] Access control logging
- [x] Change management tracking
- [x] Incident response procedures
- [x] Data encryption at rest and in transit
- [x] Regular security assessments

### ISO 27001

- [x] Information security policy
- [x] Asset management
- [x] Access control
- [x] Cryptography
- [x] Operations security

---

## 8. Monitoring & Alerting

### Prometheus Metrics

Security-related metrics exposed at `/metrics`:

```
# Cross-tenant access attempts
acs_security_cross_tenant_attempts_total{tenant_id="1"} 0

# Failed authentication rate
acs_security_failed_auth_rate{tenant_id="1"} 0.001

# Secret rotation status
acs_tenant_secret_age_days{tenant_id="1"} 45
```

### Grafana Dashboard

Import the security dashboard from `monitoring/grafana/security-dashboard.json` for visualization of:

- Real-time anomaly detection
- Failed authentication trends
- Cross-tenant access attempts
- Secret rotation compliance

---

## 9. Incident Response

### Automated Responses

| Event | Automatic Action |
|-------|-----------------|
| 5+ failed auth attempts | Temporary IP block (15 min) |
| Cross-tenant token reuse | Immediate token revocation |
| Rate limit violation | Request throttling |
| mTLS failure | Request rejection |

### Manual Response Procedures

1. **Critical Alert**: Investigate within 15 minutes
2. **High Alert**: Investigate within 1 hour
3. **Medium Alert**: Review within 24 hours
4. **Low Alert**: Include in weekly security review

---

## 10. Quick Start Checklist

```bash
# 1. Enable anomaly detection
export TENANT_ANOMALY_DETECTION=true

# 2. Configure alert channels
export TENANT_ALERT_CHANNELS=log,database,email
export TENANT_ALERT_EMAILS=security@example.com

# 3. Set rotation policy
export TENANT_SECRET_ROTATION_DAYS=90

# 4. Run initial secret rotation
php artisan tenant:rotate-secrets

# 5. Verify security logging
tail -f storage/logs/security.log
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | December 2025 | Initial Phase 4 implementation |
