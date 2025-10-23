# ACS Monitoring & Observability Guide

## Overview

This guide provides comprehensive instructions for setting up and using the ACS monitoring infrastructure powered by Prometheus, Grafana, and AlertManager.

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    ACS Application                       │
│              (Laravel + Custom Metrics)                  │
│                   /metrics endpoint                      │
└─────────────────────┬────────────────────────────────────┘
                      │ HTTP GET /metrics (30s interval)
                      ▼
         ┌────────────────────────┐
         │      Prometheus        │
         │  (Metrics Collection   │
         │   & Alert Evaluation)  │
         └──┬──────────────────┬──┘
            │                  │
            │                  │ Alerts
            ▼                  ▼
    ┌───────────────┐   ┌──────────────┐
    │    Grafana    │   │ AlertManager │
    │ (Visualization)│   │  (Routing)   │
    └───────────────┘   └──┬───────────┘
                           │
                           ▼
                    ┌──────────────┐
                    │Email/Slack/  │
                    │  PagerDuty   │
                    └──────────────┘
```

## Components

### 1. Prometheus

- **Purpose**: Time-series database for metrics storage and querying
- **Retention**: 30 days (configurable)
- **Scrape Interval**: 30 seconds
- **Storage**: Persistent volume or cloud storage

### 2. Grafana

- **Purpose**: Visualization and dashboarding
- **Dashboards**: Pre-built ACS dashboards included
- **Data Source**: Prometheus
- **Authentication**: Admin user with configurable password

### 3. AlertManager

- **Purpose**: Alert routing and notification
- **Channels**: Email, Slack, PagerDuty, Webhook
- **Grouping**: By alertname, cluster, service
- **Inhibition**: Critical alerts suppress warnings

### 4. ACS Metrics Exporter

- **Endpoint**: `/metrics` (Prometheus format)
- **Controller**: `App\Http\Controllers\Api\MetricsController`
- **Storage**: Redis DB 3 (for Prometheus client state)

## Installation

### Kubernetes Deployment

#### Step 1: Install Prometheus Operator

```bash
# Add Helm repository
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

# Install kube-prometheus-stack
helm install prometheus prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  --create-namespace \
  --set prometheus.prometheusSpec.retention=30d \
  --set prometheus.prometheusSpec.storageSpec.volumeClaimTemplate.spec.resources.requests.storage=100Gi \
  --set prometheus.prometheusSpec.resources.requests.cpu=1 \
  --set prometheus.prometheusSpec.resources.requests.memory=4Gi \
  --set grafana.adminPassword=your-secure-password
```

#### Step 2: Deploy ACS ServiceMonitor

```bash
# Apply ServiceMonitor to scrape ACS /metrics
kubectl apply -f monitoring/prometheus/servicemonitor.yaml
```

#### Step 3: Deploy Alert Rules

```bash
# Apply PrometheusRule for ACS alerts
kubectl apply -f monitoring/prometheus/prometheusrule.yaml
```

#### Step 4: Import Grafana Dashboards

```bash
# Get Grafana admin password
kubectl get secret -n monitoring prometheus-grafana \
  -o jsonpath="{.data.admin-password}" | base64 --decode

# Port-forward Grafana
kubectl port-forward -n monitoring svc/prometheus-grafana 3000:80

# Open browser to http://localhost:3000
# Login: admin / <password-from-above>
# Import: monitoring/grafana/dashboards/acs-overview.json
```

### Docker Compose (Local Development)

```bash
# Start full monitoring stack
docker-compose -f docker-compose.monitoring.yml up -d

# Access services
- Prometheus: http://localhost:9090
- Grafana: http://localhost:3000 (admin/admin)
- AlertManager: http://localhost:9093

# View logs
docker-compose -f docker-compose.monitoring.yml logs -f

# Stop services
docker-compose -f docker-compose.monitoring.yml down
```

## Custom Metrics

### Device Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `acs_devices_total` | Gauge | - | Total number of CPE devices |
| `acs_devices_online` | Gauge | - | Number of online devices |
| `acs_devices_offline` | Gauge | - | Number of offline devices |
| `acs_devices_by_status` | Gauge | `status` | Devices grouped by status (online/offline/unknown) |
| `acs_devices_by_vendor` | Gauge | `vendor` | Devices grouped by manufacturer |
| `acs_devices_by_protocol` | Gauge | `protocol` | Devices by TR protocol (TR-069/TR-369) |
| `acs_devices_registered_24h` | Gauge | - | Devices registered in last 24 hours |

### Session Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `acs_tr069_active_sessions` | Gauge | - | Number of active TR-069 sessions |
| `acs_connection_requests_1h` | Gauge | - | Connection requests in last hour |
| `acs_usp_messages_1h` | Gauge | - | USP messages processed in last hour |

### Queue Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `acs_queue_jobs_pending` | Gauge | `queue` | Pending jobs by queue name |
| `acs_queue_jobs_failed` | Gauge | `queue` | Failed jobs by queue name |
| `acs_queue_jobs_failed_total` | Gauge | - | Total failed jobs across all queues |

### Alarm Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `acs_alarms_active` | Gauge | - | Total active alarms |
| `acs_alarms_critical` | Gauge | - | Critical severity alarms |
| `acs_alarms_by_severity` | Gauge | `severity` | Alarms grouped by severity level |

### System Metrics

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `acs_database_connections` | Gauge | - | PostgreSQL connection count |
| `acs_cache_hit_ratio` | Gauge | - | Redis cache hit ratio (%) |
| `acs_app_version` | Gauge | `version` | Application version info |

## Alert Rules

### Critical Alerts

**CriticalDeviceOfflineRate**
- **Condition**: >50% devices offline
- **Duration**: 5 minutes
- **Action**: Immediate investigation required

**NoDevicesOnline**
- **Condition**: All devices offline
- **Duration**: 2 minutes
- **Action**: Critical system issue - check network/ACS availability

**CriticalQueueBacklog**
- **Condition**: >5000 pending jobs
- **Duration**: 5 minutes
- **Action**: Scale up queue workers or investigate job failures

**ExcessiveFailedJobs**
- **Condition**: >100 failed jobs
- **Duration**: 10 minutes
- **Action**: Review failed job logs and fix root cause

### Warning Alerts

**HighDeviceOfflineRate**
- **Condition**: >20% devices offline
- **Duration**: 5 minutes
- **Action**: Monitor trend, prepare for escalation

**HighQueueBacklog**
- **Condition**: >1000 pending jobs
- **Duration**: 10 minutes
- **Action**: Consider scaling queue workers

**HighCriticalAlarms**
- **Condition**: >10 critical alarms active
- **Duration**: 5 minutes
- **Action**: Review alarm dashboard and device health

**LowCacheHitRatio**
- **Condition**: <70% cache hit rate
- **Duration**: 10 minutes
- **Action**: Review cache configuration and warm-up strategy

**HighDatabaseConnections**
- **Condition**: >400 connections (max 500)
- **Duration**: 5 minutes
- **Action**: Check for connection leaks or scale database

## Grafana Dashboards

### ACS Overview Dashboard

**Panels:**
1. **Total Devices** - Overall device count with thresholds
2. **Online/Offline Devices** - Real-time status
3. **Active Alarms** - Total and critical alarms
4. **TR-069 Sessions** - Active session count
5. **Device Status Over Time** - Time series graph
6. **Queue Backlog** - Pending jobs by queue
7. **Devices by Vendor** - Pie chart
8. **Failed Jobs Rate** - Job failure trends
9. **Cache Hit Ratio** - Performance gauge

**Refresh**: 30 seconds auto-refresh

**Time Range**: Last 6 hours (adjustable)

**Filters**: Environment, namespace, pod

### Creating Custom Dashboards

```json
{
  "dashboard": {
    "title": "Custom ACS Dashboard",
    "panels": [
      {
        "title": "My Metric",
        "type": "graph",
        "targets": [
          {
            "expr": "acs_devices_total",
            "legendFormat": "Total Devices"
          }
        ]
      }
    ]
  }
}
```

## AlertManager Configuration

AlertManager is configured via environment variables for security. All notification channels (Email, Slack, PagerDuty) are pre-configured in `monitoring/alertmanager/config.yml`.

### Step 1: Configure Environment Variables

Create `monitoring/alertmanager/.env` based on `.env.example`:

```bash
# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_FROM=acs-alerts@yourdomain.com
SMTP_USERNAME=alerts@yourdomain.com
SMTP_PASSWORD=your-app-password-here

# Email Recipients
EMAIL_OPS_TEAM=ops-team@yourdomain.com
EMAIL_ONCALL=oncall@yourdomain.com

# Slack Configuration
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# PagerDuty Configuration
PAGERDUTY_ROUTING_KEY=your-pagerduty-integration-key-here
```

### Step 2: Kubernetes Secrets

For Kubernetes deployments, create a secret:

```bash
# Create AlertManager secret from env file
kubectl create secret generic alertmanager-config \
  --from-env-file=monitoring/alertmanager/.env \
  -n monitoring

# Update AlertManager deployment to use secret
kubectl set env deployment/alertmanager \
  --from=secret/alertmanager-config \
  -n monitoring
```

### Step 3: Verify Notification Channels

**Email:**
- SMTP must allow app passwords or STARTTLS
- Gmail requires app-specific password (not account password)
- Test: Send test alert via AlertManager UI

**Slack:**
1. Create Slack app at https://api.slack.com/apps
2. Enable Incoming Webhooks
3. Create webhook for #acs-critical-alerts channel
4. Copy webhook URL to SLACK_WEBHOOK_URL

**PagerDuty:**
1. Create PagerDuty service integration
2. Select "Events API V2" integration type
3. Copy Integration Key to PAGERDUTY_ROUTING_KEY
4. Configure escalation policy for critical alerts

### Pre-configured Alert Routes

The AlertManager config includes three receivers:

1. **default** - All alerts → Email to ops team
2. **critical-alerts** - Critical severity → Email + Slack + PagerDuty
3. **warning-alerts** - Warning severity → Email + Slack

Critical alerts are routed to all channels with `continue: true`, ensuring maximum visibility.

## Troubleshooting

### Metrics Not Appearing

1. **Check /metrics endpoint**:
```bash
curl http://acs-app-service/metrics
```

2. **Verify ServiceMonitor**:
```bash
kubectl get servicemonitor -n acs-production
kubectl describe servicemonitor acs-metrics -n acs-production
```

3. **Check Prometheus targets**:
```bash
# Port-forward Prometheus
kubectl port-forward -n monitoring svc/prometheus-kube-prometheus-prometheus 9090:9090

# Open http://localhost:9090/targets
# Look for "acs-app" target and check status
```

### Alerts Not Firing

1. **Verify PrometheusRule**:
```bash
kubectl get prometheusrule -n acs-production
kubectl describe prometheusrule acs-alerts -n acs-production
```

2. **Check alert evaluation**:
```bash
# In Prometheus UI: http://localhost:9090/alerts
# Verify rules are loaded and expressions are valid
```

3. **Test AlertManager**:
```bash
# Port-forward AlertManager
kubectl port-forward -n monitoring svc/prometheus-kube-prometheus-alertmanager 9093:9093

# Check http://localhost:9093/#/alerts
```

### High Cardinality Issues

If metrics storage grows too large:

1. **Reduce label cardinality**: Limit vendor labels to top 20
2. **Increase scrape interval**: Change from 30s to 60s
3. **Reduce retention**: Lower from 30d to 15d
4. **Use recording rules**: Pre-aggregate metrics

```yaml
# prometheus-rules.yaml
groups:
  - name: acs.recording
    interval: 60s
    rules:
      - record: acs:devices:offline_rate
        expr: acs_devices_offline / acs_devices_total
```

## Performance Optimization

### Redis Metrics Storage

ACS uses Redis DB 3 for Prometheus client state:

```bash
# Monitor Redis metrics DB size
redis-cli -n 3 DBSIZE

# Clear stale metrics (if needed)
redis-cli -n 3 FLUSHDB
```

### Query Optimization

**Slow Query Example** (avoid):
```promql
sum(rate(acs_devices_by_vendor[5m])) by (vendor)
```

**Optimized Query**:
```promql
acs_devices_by_vendor
```

### Dashboard Performance

1. **Limit time range**: Use 6h instead of 30d for real-time dashboards
2. **Reduce resolution**: Use 1m intervals instead of 10s
3. **Cache queries**: Enable query caching in Grafana

## Security

### Securing Metrics Endpoint

Add authentication to `/metrics` route:

```php
Route::get('/metrics', [MetricsController::class, 'index'])
    ->middleware('auth:api');
```

Or use IP allowlist:

```php
Route::get('/metrics', [MetricsController::class, 'index'])
    ->middleware('throttle:60,1')
    ->middleware(function ($request, $next) {
        $allowedIPs = explode(',', env('PROMETHEUS_ALLOWED_IPS', ''));
        if (!in_array($request->ip(), $allowedIPs)) {
            abort(403);
        }
        return $next($request);
    });
```

### Grafana Security

1. **Change default password** immediately after installation
2. **Enable HTTPS** for production deployments
3. **Use LDAP/OAuth** for authentication
4. **Restrict viewer permissions** for read-only users

## Maintenance

### Backup Prometheus Data

```bash
# Kubernetes with PVC
kubectl exec -n monitoring prometheus-kube-prometheus-prometheus-0 \
  -- tar czf /tmp/prometheus-backup.tar.gz /prometheus

kubectl cp monitoring/prometheus-kube-prometheus-prometheus-0:/tmp/prometheus-backup.tar.gz \
  ./prometheus-backup-$(date +%Y%m%d).tar.gz
```

### Backup Grafana Dashboards

```bash
# Export all dashboards via API
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://grafana:3000/api/search?query=& | \
  jq -r '.[] | .uid' | \
  xargs -I {} curl -H "Authorization: Bearer YOUR_API_KEY" \
    http://grafana:3000/api/dashboards/uid/{} > dashboard-{}.json
```

### Update Prometheus Rules

```bash
# Edit prometheusrule.yaml
kubectl apply -f monitoring/prometheus/prometheusrule.yaml

# Reload Prometheus configuration
kubectl port-forward -n monitoring svc/prometheus-kube-prometheus-prometheus 9090:9090
curl -X POST http://localhost:9090/-/reload
```

## Best Practices

1. **Set meaningful alert thresholds** based on actual production data
2. **Use inhibition rules** to prevent alert storms
3. **Group related alerts** to reduce notification noise
4. **Document runbooks** for each alert in annotations
5. **Test alerts** regularly with synthetic failures
6. **Monitor monitoring** - Set alerts for Prometheus/Grafana downtime
7. **Review dashboards** monthly and remove unused panels
8. **Optimize queries** to reduce Prometheus load
9. **Archive old metrics** to object storage for long-term retention
10. **Train team** on dashboard usage and alert response

## Resources

- [Prometheus Documentation](https://prometheus.io/docs/)
- [Grafana Dashboards](https://grafana.com/docs/grafana/latest/dashboards/)
- [AlertManager Configuration](https://prometheus.io/docs/alerting/latest/configuration/)
- [PromQL Cheat Sheet](https://promlabs.com/promql-cheat-sheet/)
- [Kubernetes ServiceMonitor](https://github.com/prometheus-operator/prometheus-operator/blob/main/Documentation/user-guides/getting-started.md)
