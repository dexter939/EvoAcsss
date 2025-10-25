# CI/CD Pipeline Documentation

## Overview

Il sistema ACS implementa una pipeline CI/CD completa utilizzando **GitHub Actions** per garantire la qualità del codice, la sicurezza e la production readiness ad ogni commit e pull request.

## Pipeline Structure

La pipeline è composta da **4 job paralleli** che validano diversi aspetti del sistema:

```
┌─────────────────────────────────────────────────────┐
│              GitHub Actions Workflow                │
│                  (.github/workflows/tests.yml)      │
└─────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┬─────────────────┐
        │                 │                 │                 │
        ▼                 ▼                 ▼                 ▼
   ┌────────┐      ┌──────────┐      ┌──────────┐     ┌────────┐
   │  Test  │      │   Lint   │      │   MQTT   │     │   K6   │
   │  Suite │      │  (Pint)  │      │  Health  │     │  Load  │
   │        │      │          │      │  Check   │     │ Testing│
   └────────┘      └──────────┘      └──────────┘     └────────┘
```

---

## Job 1: Test Suite

### Purpose
Esegue la suite completa di test PHPUnit/Pest con coverage report.

### Services
- **PostgreSQL 16**: Database per test
- **Redis 7**: Cache e queue driver
- **Mosquitto MQTT**: Broker per test USP

### Steps
1. ✅ Setup PHP 8.3 con estensioni (protobuf, soap, redis)
2. ✅ Install Composer dependencies
3. ✅ Setup database schema (Drizzle push)
4. ✅ Run PHPUnit con coverage Xdebug
5. ✅ Upload coverage a Codecov

### Environment Variables
```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
REDIS_HOST=localhost
REDIS_PORT=6379
ACS_API_KEY=test-api-key-for-ci
```

### Success Criteria
- ✅ Tutti i test passano (exit code 0)
- ✅ Code coverage > 70% (recommended)
- ✅ Nessun errore PHP Fatal/Parse

---

## Job 2: Lint (Laravel Pint)

### Purpose
Valida code style secondo PSR-12 standard.

### Steps
1. ✅ Setup PHP 8.3
2. ✅ Install Composer dependencies
3. ✅ Run `vendor/bin/pint --test`

### Success Criteria
- ✅ Nessuna violazione PSR-12
- ✅ Code style consistente

---

## Job 3: MQTT Health Check ⭐ NEW

### Purpose
Valida la configurazione MQTT e la connettività al broker per TR-369 USP transport.

### Services
- **Mosquitto MQTT 2.0**: Eclipse Mosquitto broker
  - Port 1883: MQTT
  - Port 9001: WebSocket
  - Health check: `mosquitto_sub -t 'test' -C 1 -W 1`

### Steps
1. ✅ Setup PHP 8.3 con estensioni MQTT
2. ✅ Install Composer dependencies
3. ✅ Configure MQTT environment variables
   ```bash
   MQTT_HOST=localhost
   MQTT_PORT=1883
   MQTT_CLIENT_ID=acs-ci-test
   ```
4. ✅ Wait for MQTT broker readiness (30s timeout)
5. ✅ Run `php artisan mqtt:health-check --timeout=5 --fail-fast`
6. ✅ Generate MQTT Health Check Summary

### Validation Performed
- ✅ **35 MQTT environment variables** validated
- ✅ **Broker connectivity** tested (actual network I/O)
- ✅ **Publish/Subscribe operations** verified
- ✅ **TLS configuration** checked (if enabled)
- ✅ **Auto-reconnect settings** validated
- ✅ **Last Will Testament** configuration verified

### Success Criteria
- ✅ MQTT broker reachable on localhost:1883
- ✅ Publish operation successful
- ✅ All required env vars present
- ✅ Exit code 0 (fail-fast mode)

### Failure Handling
Se il health check fallisce, il job:
1. ❌ Exit con codice 1 (fail-fast)
2. 📋 Mostra troubleshooting hints:
   - Verify MQTT broker status
   - Check firewall rules
   - Test with mosquitto_sub/pub
   - Review broker logs

---

## Job 4: K6 Load Testing ⭐ NEW

### Purpose
Esegue **functional validation** del sistema ACS sotto carico utilizzando K6.

### Services
- **PostgreSQL 16**: Database completo
- **Redis 7**: Cache e queue

### Infrastructure
- **PHP 8.3** con estensioni (protobuf, pdo_pgsql, redis)
- **K6 v0.48.0**: Load testing tool
- **Laravel development server**: Port 5000

### Steps
1. ✅ Setup PHP e dependencies
2. ✅ Setup Laravel application
3. ✅ Run database migrations (`npm run db:push --force`)
4. ✅ Start Laravel server (background)
5. ✅ Install K6 binary
6. ✅ Run `k6 run tests/Load/scenarios/tr369-ci.js`
7. ✅ Upload K6 results as artifacts
8. ✅ Generate test summary

### Test Scenarios Executed

#### TR-369 USP CI/CD Validation
Script: `tests/Load/scenarios/tr369-ci.js`

**CI-Optimized for GitHub Actions**:
- Lightweight (5-10 VUs max)
- Fast (~2.5 minutes total)
- Minimal resource usage
- Strict functional thresholds

**Message Types Tested**:
- GET (40%)
- SET (20%)
- GET_INSTANCES (15%)
- GET_SUPPORTED_DM (15%)
- GET_SUPPORTED_PROTOCOL (10%)

**Load Profile** (GitHub Actions friendly):
```javascript
stages: [
  { duration: '30s', target: 5 },   // Ramp-up to 5 VUs
  { duration: '1m', target: 5 },    // Sustain 5 VUs
  { duration: '30s', target: 10 },  // Brief spike to 10 VUs
  { duration: '30s', target: 0 }    // Ramp-down
]
```

**Strict Thresholds** (Production-Ready):
```javascript
thresholds: {
  http_req_failed: ['rate<0.02'],        // Error rate < 2%
  http_req_duration: ['p(95)<2000'],     // p95 < 2s
  'http_req_duration{operation:GET}': ['p(95)<1000'],
  'http_req_duration{operation:SET}': ['p(95)<1500'],
  checks: ['rate>0.95']                  // Success rate > 95%
}
```

### Success Criteria
- ✅ All thresholds passed
- ✅ Error rate < 2%
- ✅ Success rate > 95%
- ✅ p95 response time < 2s
- ✅ No database errors

### Artifacts Generated
- `k6-results.json`: Detailed metrics
- `k6-summary.json`: Test summary
- Available for download in GitHub Actions artifacts

### CI vs Local Testing

**CI/CD Pipeline** (`tr369-ci.js`):
- ✅ Lightweight (5-10 VUs)
- ✅ Fast (~2.5 minutes)
- ✅ GitHub Actions runner friendly
- ✅ Blocking failures (strict thresholds enforced)
- 🎯 Use for: Pre-merge validation, smoke testing

**Local/Production Testing** (`tr369-functional.js`):
- 🚀 Heavy load (up to 30K VUs)
- ⏱️ Long duration (~44 minutes)
- 💪 Full production simulation
- 📊 Comprehensive metrics
- 🎯 Use for: Performance testing, capacity planning, pre-deployment validation

---

## Triggers

### Push Events
```yaml
on:
  push:
    branches: [ main, develop ]
```

La pipeline si attiva automaticamente su push a:
- `main`: Production branch
- `develop`: Development branch

### Pull Request Events
```yaml
on:
  pull_request:
    branches: [ main, develop ]
```

La pipeline si attiva su PR verso:
- `main`: Production deployment
- `develop`: Feature integration

---

## Parallel Execution

Tutti i **4 job** vengono eseguiti **in parallelo** per massimizzare la velocità:

```
Start Time: T+0s
├─ test            (duration: ~3min)
├─ lint            (duration: ~1min)
├─ mqtt-health     (duration: ~1min)
└─ k6-load-testing (duration: ~4min)

Total Pipeline Duration: ~4min (max job duration)
```

### Optimization Benefits
- ⚡ **4x faster** rispetto a sequential execution
- 🎯 **Early feedback** su multiple dimensioni
- 💰 **Cost efficient** (GitHub Actions minutes)

---

## Environment Variables Required

### Test Job
```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
REDIS_HOST=localhost
REDIS_PORT=6379
ACS_API_KEY=test-api-key-for-ci
```

### MQTT Health Check Job
```env
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_CLIENT_ID=acs-ci-test
```

### K6 Load Testing Job
```env
DATABASE_URL=postgresql://acs_user:acs_pass@localhost:5432/acs_test
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=acs_test
DB_USERNAME=acs_user
DB_PASSWORD=acs_pass
```

---

## GitHub Actions Secrets

### Required Secrets
Nessun secret obbligatorio per i job base.

### Optional Secrets
- `CODECOV_TOKEN`: Per upload coverage report a Codecov
  - Configurato in: Settings → Secrets → Actions
  - Usato da: Test job → Upload coverage step

---

## Failure Scenarios & Debugging

### Test Job Failure
**Possibili cause**:
- ❌ Test PHPUnit falliti
- ❌ Syntax error PHP
- ❌ Database migration error

**Debug**:
1. Visualizza logs del job nel GitHub Actions UI
2. Controlla output PHPUnit dettagliato
3. Esegui localmente: `php artisan test`

### Lint Job Failure
**Possibili cause**:
- ❌ Code style violations (PSR-12)

**Debug**:
1. Esegui localmente: `vendor/bin/pint --test`
2. Auto-fix: `vendor/bin/pint`
3. Review diff e commit changes

### MQTT Health Check Failure
**Possibili cause**:
- ❌ MQTT broker non raggiungibile
- ❌ Environment variables mancanti
- ❌ Publish/subscribe fallito

**Debug**:
1. Check MQTT broker logs in job output
2. Verify Mosquitto service health
3. Test manualmente:
   ```bash
   php artisan mqtt:health-check --timeout=10
   ```

### K6 Load Testing Failure
**Possibili cause**:
- ❌ Thresholds non rispettati (error rate > 2%)
- ❌ Laravel server crash
- ❌ Database timeout

**Debug**:
1. Download artifacts: `k6-results.json`, `k6-summary.json`
2. Analizza metrics e error patterns
3. Test localmente:
   ```bash
   k6 run tests/Load/scenarios/tr369-functional.js
   ```

---

## CI/CD Best Practices

### ✅ DO
- ✅ Esegui tutti i test prima di merge
- ✅ Mantieni thresholds strict (error rate < 2%)
- ✅ Review coverage reports regolarmente
- ✅ Fix linting violations immediatamente
- ✅ Monitor K6 metrics trends

### ❌ DON'T
- ❌ Merge PR con test falliti
- ❌ Ignorare linting violations
- ❌ Skip MQTT health check in production
- ❌ Deploy senza K6 validation

---

## Integration with Deployment

### Pre-Deployment Checklist
Prima di ogni production deployment, assicurati che:

1. ✅ **All CI/CD jobs passed** (green checkmark)
2. ✅ **MQTT health check succeeded** (broker connectivity OK)
3. ✅ **K6 thresholds met** (error rate < 2%, success rate > 95%)
4. ✅ **Code coverage acceptable** (> 70% recommended)
5. ✅ **No linting violations** (PSR-12 compliant)

### Automated Deployment Trigger
```yaml
# .github/workflows/deploy.yml (future)
on:
  workflow_run:
    workflows: ["Tests"]
    types:
      - completed
    branches:
      - main

jobs:
  deploy:
    if: ${{ github.event.workflow_run.conclusion == 'success' }}
    runs-on: ubuntu-latest
    steps:
      # Deploy to production only if Tests workflow passed
```

---

## Monitoring & Metrics

### GitHub Actions Dashboard
- **Location**: Repository → Actions tab
- **Metrics tracked**:
  - ✅ Success rate per job
  - ⏱️ Average execution time
  - 📊 Trends over time
  - 🔴 Failure patterns

### Codecov Integration
- **Dashboard**: https://codecov.io/gh/YOUR_ORG/acs
- **Metrics**:
  - Line coverage %
  - Branch coverage %
  - Coverage diff per PR
  - Sunburst visualization

---

## Local Testing

### Run CI/CD locally con Act
```bash
# Install Act (GitHub Actions local runner)
curl https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash

# Run entire workflow
act -j test
act -j lint
act -j mqtt-health-check
act -j k6-load-testing

# Run all jobs in parallel
act
```

### Docker Compose per CI environment
```bash
# Spin up services (PostgreSQL, Redis, Mosquitto)
docker-compose -f docker-compose.test.yml up -d

# Run tests
php artisan test

# MQTT health check
php artisan mqtt:health-check --timeout=5 --fail-fast

# K6 load testing
k6 run tests/Load/scenarios/tr369-functional.js
```

---

## Troubleshooting

### "MQTT broker connection refused"
```bash
# Check Mosquitto service
docker ps | grep mosquitto

# Test connection manually
mosquitto_sub -h localhost -p 1883 -t test

# Check logs
docker logs <mosquitto_container_id>
```

### "K6 thresholds failed"
```bash
# Download artifacts from GitHub Actions
# Analyze k6-summary.json

# Check specific metrics
jq '.metrics.http_req_duration' k6-summary.json
jq '.metrics.http_req_failed' k6-summary.json

# Run locally with verbose output
k6 run --verbose tests/Load/scenarios/tr369-functional.js
```

### "Database migration error"
```bash
# Force push schema
npm run db:push -- --force

# Check PostgreSQL logs
docker logs <postgres_container_id>

# Verify connection
psql postgresql://acs_user:acs_pass@localhost:5432/acs_test
```

---

## Future Enhancements

### Planned Improvements
- [ ] **Automated deployment** to production on main branch
- [ ] **Performance regression detection** (K6 trend analysis)
- [ ] **Security scanning** (OWASP dependency check)
- [ ] **Docker image building** and push to registry
- [ ] **Helm chart validation** for Kubernetes deployments
- [ ] **Slack/Discord notifications** on failure
- [ ] **Nightly load testing** con 100K devices simulation

---

## Summary

Il CI/CD pipeline ACS garantisce:

✅ **Quality**: Test suite completa + linting  
✅ **Reliability**: MQTT health check + database validation  
✅ **Performance**: K6 functional validation con strict thresholds  
✅ **Production Readiness**: Fail-fast su errori critici  
✅ **Fast Feedback**: Pipeline completa in ~4 minuti  

**Total Coverage**:
- 🧪 Unit + Integration tests (PHPUnit/Pest)
- 🎨 Code style (Laravel Pint)
- 📡 MQTT connectivity (Health check)
- ⚡ Performance (K6 load testing)
- 📊 Coverage tracking (Codecov)

**Carrier-Grade CI/CD for 100K+ Device Management** 🚀
