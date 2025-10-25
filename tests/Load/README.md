# Load Testing Infrastructure

## Overview

Questa directory contiene l'infrastruttura di load testing per il sistema ACS carrier-grade, progettata per validare le performance con 100,000+ dispositivi CPE simultanei.

## Strumenti

**K6** - Modern load testing tool per backend APIs e protocolli

- JavaScript-based test scripts
- Rich metrics e reporting
- Integration con Prometheus/Grafana
- Support per HTTP, WebSocket, gRPC

## Struttura Directory

```
tests/Load/
├── scenarios/          # Test scenarios per diversi livelli di carico
│   ├── api-rest.js         # REST API load testing
│   ├── tr069.js            # TR-069 protocol testing (infrastructure)
│   ├── tr369.js            # TR-369 USP protocol testing (infrastructure)
│   ├── tr369-functional.js # TR-369 USP functional validation (local)
│   ├── tr369-ci.js         # TR-369 USP CI/CD validation (lightweight)
│   └── mixed.js            # Mixed protocol scenarios
├── utils/             # Utility functions e helpers
│   ├── config.js      # Configuration management
│   ├── metrics.js     # Custom metrics
│   └── generators.js  # Data generators
├── reports/           # Test results e reports
└── README.md          # Questa documentazione
```

## Installation

### K6 Installation

```bash
# Download K6 (Linux)
curl -L https://github.com/grafana/k6/releases/download/v0.48.0/k6-v0.48.0-linux-amd64.tar.gz -o k6.tar.gz
tar -xzf k6.tar.gz
mv k6-v0.48.0-linux-amd64/k6 /usr/local/bin/
chmod +x /usr/local/bin/k6

# Verify installation
k6 version
```

### Dependencies

```bash
# Install K6 extensions (optional)
k6 install github.com/grafana/xk6-output-prometheus-remote@latest
```

## Test Scenarios

### ⚠️ Important: Testing Modes

Load testing può essere eseguito in 2 modalità:

**1. Infrastructure Hardening Mode** (endpoints non completamente implementati):
- Focus: Database performance, connection pooling, queue processing
- Acceptable: 404 responses per endpoints non implementati
- Thresholds applicabili: Response time, throughput
- Thresholds NON applicabili: Error rate, success rate
- **Use case**: Validare che infrastructure regge il carico, anche se alcuni endpoints mancano

**2. Functional Validation Mode** (tutti endpoints implementati):
- Focus: End-to-end functionality sotto carico
- Required: 200 responses per tutti i requests
- Thresholds applicabili: TUTTI (response time, error rate, success rate)
- **Use case**: Validare sistema completo prima di production deployment

**Configurazione Thresholds**:

Gli script K6 hanno 2 set di thresholds:

1. **Performance Thresholds** (sempre abilitati):
   - Response time (p95, p99)
   - Throughput
   - Focus: Validare infrastructure performance

2. **Functional Thresholds** (commentati di default):
   - Error rate
   - Success rate
   - Focus: Validare functional correctness

**Per Infrastructure Hardening** (default):
```bash
# Usa script così come sono - solo performance thresholds
./tests/Load/run-tests.sh tr369
```

**Per Functional Validation**:
```javascript
// In tests/Load/scenarios/tr369.js e mixed.js
// Decommentare le righe:
'http_req_failed': ['rate<0.02'],
'tr369_usp_operation_success_rate': ['rate>0.95'],
```

Poi eseguire:
```bash
./tests/Load/run-tests.sh tr369
```

---

### 1. REST API Load Testing

Testa le API REST per device management, search, filtering.

```bash
k6 run tests/Load/scenarios/api-rest.js
```

**Metrics**:
- Request throughput (req/s)
- Response time (p95, p99)
- Error rate
- Database connection pool usage

**Thresholds** (Functional Validation Mode):
- ✅ p95 < 500ms
- ✅ p99 < 1000ms
- ✅ Error rate < 1%

### 2. TR-069 Protocol Testing

Simula connessioni TR-069 da 100K+ dispositivi CPE.

```bash
k6 run tests/Load/scenarios/tr069.js
```

**Operations Tested**:
- Inform messages (device registration)
- GetParameterValues requests
- SetParameterValues updates
- Connection Request authentication

**Thresholds**:
- ✅ 1000+ concurrent TR-069 sessions
- ✅ Inform processing < 200ms
- ✅ Parameter operations < 500ms

### 3. TR-369 USP Protocol Testing

Testa USP over HTTP/MQTT/WebSocket con Protocol Buffers encoding.

**✅ Endpoints Implementati**:
- USP HTTP endpoint: `/tr369/usp` ✅
- USP MQTT bridge: `/tr369/mqtt/publish` ✅
- USP WebSocket server: `php artisan usp:websocket-server` ✅

**🎯 USP Message Types Supportati**:
1. **GET** (40%) - Parameter value retrieval
2. **SET** (20%) - Parameter configuration
3. **GET_INSTANCES** (15%) - Multi-instance object enumeration
4. **GET_SUPPORTED_DM** (15%) - TR-181 data model metadata
5. **GET_SUPPORTED_PROTOCOL** (10%) - USP protocol version negotiation

**Infrastructure Hardening Mode** (default - focus on performance):
```bash
# Run with performance thresholds only
./tests/Load/run-tests.sh tr369
# or directly: k6 run tests/Load/scenarios/tr369.js
```

**Functional Validation Mode** (validazione completa - PRODUCTION READY):
```bash
# Use production-ready script with strict functional thresholds
k6 run tests/Load/scenarios/tr369-functional.js

# For mixed protocol with functional validation
k6 run tests/Load/scenarios/mixed-functional.js
```

**Note**: Functional validation scripts require ALL endpoints to return 200 responses. They will fail if endpoints are not fully implemented.

**Transports Tested**:
- **HTTP POST** (40%) - Bulk operations, stateless requests
- **MQTT pub/sub** (30%) - Real-time messaging via broker
- **WebSocket** (30%) - Persistent bidirectional connections

**Note**: Mock JSON payloads sono usati per load testing infrastructure. In produzione, USP usa base64-encoded Protocol Buffers binary format per tutti i transports.

**Thresholds** (Functional Validation Mode ONLY):
- ✅ HTTP p95 < 400ms, p99 < 800ms
- ✅ MQTT p95 < 200ms, p99 < 400ms
- ✅ WebSocket p95 < 300ms, p99 < 600ms
- ✅ USP operation success rate > 95%
- ✅ Error rate < 2%

**Thresholds** (Infrastructure Hardening Mode):
- ✅ Response time metrics (performance validation)
- ⚠️  Error rate skipped (404s acceptable for unimplemented operations)
- ⚠️  Success rate skipped (focus on infrastructure capacity)

### 4. Mixed Protocol Scenario

Simula produzione reale con mix di TR-069, TR-369, REST API.

```bash
k6 run tests/Load/scenarios/mixed.js
```

**Protocol Distribution**:
- **60% TR-069 sessions** (legacy CWMP devices)
- **30% TR-369 USP** (modern devices)
  - 70% HTTP transport
  - 30% MQTT transport
  - All 5 message types (GET, SET, GET_INSTANCES, GET_SUPPORTED_DM, GET_SUPPORTED_PROTOCOL)
- **10% REST API calls** (admin/management operations)

**Peak Load Target**: 100,000 concurrent devices

## Scalability Testing

### Progressive Load Test

Test con carico crescente per identificare breaking point:

```bash
# Stage 1: 1K devices (warm-up)
# Stage 2: 10K devices (normal load)
# Stage 3: 50K devices (high load)
# Stage 4: 100K devices (peak load)
# Stage 5: 150K devices (stress test)
k6 run --vus 150000 --duration 30m tests/Load/scenarios/mixed.js
```

### Soak Test

Test di stabilità prolungata (4+ ore):

```bash
k6 run --vus 50000 --duration 4h tests/Load/scenarios/mixed.js
```

### Spike Test

Test di resilienza a picchi improvvisi:

```bash
k6 run tests/Load/scenarios/spike-test.js
```

## Metrics Collection

### K6 Native Metrics

K6 raccoglie automaticamente:
- `http_reqs` - Total HTTP requests
- `http_req_duration` - Request duration
- `http_req_failed` - Failed requests
- `vus` - Virtual users
- `iterations` - Completed iterations

### Custom Metrics

```javascript
import { Counter, Trend, Rate } from 'k6/metrics';

const tr069Sessions = new Counter('tr069_sessions_total');
const tr369Latency = new Trend('tr369_usp_latency');
const deviceRegistrations = new Rate('device_registration_success');
```

### Prometheus Integration

K6 può esportare metrics direttamente a Prometheus:

```bash
k6 run --out prometheus-remote-write=http://localhost:9090/api/v1/write tests/Load/scenarios/api-rest.js
```

### Grafana Dashboards

Import pre-configured dashboards:
- K6 Load Testing Results
- ACS Performance Overview
- Protocol-specific metrics (TR-069, TR-369)

## Performance Thresholds

### Database Performance

- **Query execution time**: p95 < 50ms, p99 < 100ms
- **Connection pool**: < 80% utilization
- **Transaction throughput**: > 10K/sec

### Application Performance

- **CPU usage**: < 70% average
- **Memory usage**: < 80% of available RAM
- **Response time**: p95 < 500ms

### Network Performance

- **Bandwidth utilization**: < 70%
- **Packet loss**: < 0.1%
- **Latency**: < 50ms (internal)

## Bottleneck Identification

K6 reports identificano automaticamente:

1. **Database bottlenecks**
   - Slow queries
   - Connection pool exhaustion
   - Lock contention

2. **Application bottlenecks**
   - CPU-bound operations
   - Memory leaks
   - Queue processing delays

3. **Network bottlenecks**
   - Bandwidth saturation
   - Connection limits
   - Timeout issues

## Optimization Workflow

1. **Baseline**: Esegui test con carico attuale
2. **Profile**: Identifica bottleneck con K6 reports
3. **Optimize**: Applica fix (indexing, caching, query optimization)
4. **Validate**: Re-run test per verificare miglioramenti
5. **Document**: Aggiorna reports con risultati

## Results Analysis

### HTML Report

```bash
k6 run --out json=reports/results.json tests/Load/scenarios/api-rest.js
k6 report --input reports/results.json --output reports/results.html
```

### JSON Export

```bash
k6 run --out json=reports/results.json tests/Load/scenarios/api-rest.js
```

### Summary Statistics

K6 stampa automaticamente summary a fine test:

```
✓ status is 200
✓ response time < 500ms

checks.........................: 99.95% ✓ 149925  ✗ 75
data_received..................: 1.2 GB  40 MB/s
data_sent......................: 89 MB   3.0 MB/s
http_req_duration..............: avg=234ms min=45ms med=198ms max=2.1s p(95)=456ms p(99)=789ms
http_reqs......................: 150000  5000/s
vus............................: 1000    min=1000 max=1000
```

## Production Deployment Validation

**Phase 1: Infrastructure Hardening** (current phase)
```bash
# Run tests with performance thresholds only (default)
./tests/Load/run-tests.sh smoke  # Quick validation
./tests/Load/run-tests.sh mixed  # Full 100K load test
```

**Obiettivo**: Validare che database, cache, queues reggono 100K+ devices

**Checklist**:
- ✅ Database query performance (p95 < 50ms)
- ✅ Connection pool efficiency (< 80% utilization)
- ✅ Cache hit ratio (> 80%)
- ✅ Queue processing rate (> 1000 jobs/sec)
- ✅ Memory usage stable (< 80% RAM)
- ⚠️  Error rates NON applicabili (endpoints non implementati)

---

**Phase 2: Functional Validation** (before production deployment)

Prerequisites:
- ✅ Tutti TR-069 endpoints implementati
- ✅ Tutti TR-369 USP endpoints implementati (HTTP/MQTT/WebSocket)
- ✅ Tutti REST API endpoints implementati

```bash
# Run tests WITH all thresholds
./tests/Load/run-tests.sh api      # 1K users
./tests/Load/run-tests.sh tr069    # 50K devices
./tests/Load/run-tests.sh tr369    # 30K sessions
./tests/Load/run-tests.sh mixed    # 100K total
./tests/Load/run-tests.sh soak     # 24h stability
```

**Checklist**:
- ✅ API REST test (10K concurrent users)
- ✅ TR-069 protocol test (50K devices)
- ✅ TR-369 USP test (30K devices, all transports)
- ✅ Mixed scenario test (100K total load)
- ✅ Soak test (24h stability)
- ✅ Error rate < 1% for ALL tests
- ✅ Success rate > 99% for ALL tests

## Prometheus/Grafana Integration

### Overview

K6 load test metrics possono essere esportati a Prometheus e visualizzati in Grafana in real-time.

**Architecture**:
```
K6 Test → JSON Output → Prometheus Exporter → Prometheus → Grafana Dashboard
```

### Quick Start

**Step 1: Start Monitoring Stack**
```bash
# Start Prometheus & Grafana
docker-compose -f docker-compose.monitoring.yml up -d

# Verify services
curl http://localhost:9090  # Prometheus
curl http://localhost:3000  # Grafana (admin/admin)
```

**Step 2: Run Load Test with Prometheus Export**
```bash
# Run test with automatic Prometheus export
./tests/Load/run-with-prometheus.sh mixed

# Available scenarios:
./tests/Load/run-with-prometheus.sh smoke   # Quick test
./tests/Load/run-with-prometheus.sh api     # REST API
./tests/Load/run-with-prometheus.sh tr069   # TR-069
./tests/Load/run-with-prometheus.sh tr369   # TR-369
./tests/Load/run-with-prometheus.sh mixed   # All protocols
```

**Step 3: View Results in Grafana**
```
1. Open: http://localhost:3000
2. Login: admin/admin (change password on first login)
3. Navigate to: Dashboards → ACS - K6 Load Testing
4. Watch real-time metrics during test execution
```

### Available Metrics

**Built-in K6 Metrics**:
- `k6_http_req_duration{quantile}` - HTTP response time (p50, p95, p99)
- `k6_http_reqs_total` - Total HTTP requests
- `k6_http_req_failed_total` - Failed requests
- `k6_vus` - Current virtual users
- `k6_iterations_total` - Total iterations

**Custom Protocol Metrics**:
- `k6_tr069_inform_duration{quantile}` - TR-069 Inform duration
- `k6_tr069_inform_success_rate` - TR-069 success rate
- `k6_tr369_usp_operation_duration{quantile}` - TR-369 USP duration
- `k6_tr369_http_messages_total` - TR-369 HTTP transport messages
- `k6_tr369_mqtt_messages_total` - TR-369 MQTT transport messages
- `k6_tr369_websocket_messages_total` - TR-369 WebSocket messages
- `k6_api_request_duration{quantile}` - API request duration

### Grafana Dashboard

**Dashboard**: ACS - K6 Load Testing

**Panels**:
1. **Virtual Users** - Current VU count over time
2. **Request Rate** - Requests per second
3. **Error Rate** - Percentage of failed requests (with alerts)
4. **HTTP Response Time** - p50, p95, p99 percentiles
5. **Total Requests** - Cumulative request count
6. **Total Iterations** - Cumulative iteration count
7. **TR-069 Inform Duration** - CWMP protocol performance
8. **TR-369 USP Duration** - USP protocol performance
9. **API Request Duration** - REST API performance
10. **TR-369 Transport Messages** - HTTP/MQTT/WebSocket distribution
11. **Success Rates by Protocol** - TR-069/TR-369/API success rates

**Alerts**:
- High HTTP Response Time (p95 > 800ms)

### Manual Prometheus Export

Se preferisci più controllo, puoi eseguire manualmente:

```bash
# Terminal 1: Run K6 with JSON output
k6 run --out json=test-results.json tests/Load/scenarios/mixed.js

# Terminal 2: Start Prometheus exporter
node tests/Load/utils/prometheus-exporter.js test-results.json

# Terminal 3: Query metrics
curl http://localhost:9091/metrics
curl http://localhost:9091/health
```

### Prometheus Configuration

**Scrape Configuration** (`monitoring/prometheus/prometheus.yml`):
```yaml
scrape_configs:
  - job_name: 'k6-load-testing'
    static_configs:
      - targets: ['localhost:9091']
    metrics_path: '/metrics'
    scrape_interval: 5s
    scrape_timeout: 3s
```

**Query Examples**:
```promql
# Average response time (last 5 minutes)
avg(k6_http_req_duration{quantile="0.95"}[5m])

# Error rate percentage
rate(k6_http_req_failed_total[1m]) / rate(k6_http_reqs_total[1m]) * 100

# Requests per second
rate(k6_http_reqs_total[1m])

# Virtual users trend
k6_vus

# TR-369 transport distribution
sum(rate(k6_tr369_http_messages_total[5m]))
sum(rate(k6_tr369_mqtt_messages_total[5m]))
sum(rate(k6_tr369_websocket_messages_total[5m]))
```

### Production Monitoring

Per monitoraggio production durante load testing:

**1. Long-term Storage**:
```yaml
# prometheus.yml
global:
  scrape_interval: 30s
  storage:
    tsdb:
      retention.time: 30d
```

**2. Alerting Rules** (`monitoring/prometheus/rules/k6-alerts.yml`):
```yaml
groups:
  - name: k6_load_testing
    rules:
      - alert: HighErrorRate
        expr: rate(k6_http_req_failed_total[5m]) > 0.05
        for: 5m
        annotations:
          summary: "K6 error rate > 5%"
      
      - alert: HighResponseTime
        expr: k6_http_req_duration{quantile="0.95"} > 1000
        for: 5m
        annotations:
          summary: "K6 p95 response time > 1s"
```

**3. Export Reports**:
```bash
# Export Grafana dashboard as PDF/PNG
# Grafana → Dashboard → Share → Export
```

## Troubleshooting

### K6 Errors

**Error: Too many open files**
```bash
ulimit -n 65536
```

**Error: Connection refused**
- Verificare che ACS server sia running
- Controllare firewall rules
- Validare network connectivity

**Error: Out of memory**
- Ridurre VUs (virtual users)
- Aumentare RAM disponibile
- Ottimizzare script (rimuovere logging eccessivo)

### Performance Issues

**High latency**: 
- Check database query performance
- Verify Redis cache hit ratio
- Review application logs

**High error rate**:
- Check application error logs
- Verify database connectivity
- Review rate limiting configuration

## Resources

- **K6 Documentation**: https://k6.io/docs/
- **K6 Examples**: https://github.com/grafana/k6-learn
- **Prometheus Integration**: https://k6.io/docs/results-output/real-time/prometheus-remote-write/
- **Best Practices**: https://k6.io/docs/testing-guides/api-load-testing/

## Contact

Per domande o supporto: vedere documentazione principale ACS
