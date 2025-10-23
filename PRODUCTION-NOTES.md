# ⚠️ ACS Production Deployment Notes

## Critical Production Considerations

### 🚨 High Availability Requirements

Il deployment base fornito in `docker-compose.yml` è progettato per **staging/testing** e **deployments single-server**. 

**Per ambienti production carrier-grade (100K+ dispositivi), è richiesta un'architettura High Availability:**

---

## ❌ Limitations del Deployment Base

### Single Points of Failure:
- ✗ **PostgreSQL**: Single instance senza replica
- ✗ **Redis**: Single instance senza Sentinel/Cluster
- ✗ **ACS App**: Single container senza load balancing
- ✗ **Nginx**: Single instance in-container
- ✗ **Prosody XMPP**: Single instance

### Mancanze per Production:
- ✗ Database replication (streaming replication)
- ✗ Redis HA (Sentinel o Cluster mode)
- ✗ Horizontal scaling multi-instance
- ✗ External load balancer
- ✗ Distributed tracing
- ✗ Centralized logging
- ✗ Metrics collection (Prometheus/Grafana)

---

## ✅ Architettura Production Raccomandata

### Per Production Carrier-Grade (100K+ Devices):

```
┌─────────────────────────────────────────┐
│     External Load Balancer (HAProxy)    │
│         + SSL Termination (Nginx)       │
└────────────┬────────────────────────────┘
             │
   ┌─────────┼──────────┐
   │         │          │
┌──▼──┐  ┌──▼──┐  ┌───▼──┐
│ACS-1│  │ACS-2│  │ACS-3 │  (3+ instances)
└──┬──┘  └──┬──┘  └───┬──┘
   └─────────┼──────────┘
             │
   ┌─────────┼──────────────┐
   │         │              │
┌──▼────────────┐  ┌────────▼──────┐  ┌────────▼─────┐
│ PostgreSQL HA │  │  Redis Cluster │  │  Prosody HA  │
│  (Patroni +   │  │ (Sentinel 3x)  │  │  (Clustered) │
│   Standby)    │  │                │  │              │
└───────────────┘  └────────────────┘  └──────────────┘
```

### Managed Services Raccomandati:

1. **Database**: 
   - AWS RDS PostgreSQL (Multi-AZ)
   - Google Cloud SQL for PostgreSQL (HA)
   - Azure Database for PostgreSQL (Zone-redundant)

2. **Cache/Queue**:
   - AWS ElastiCache Redis (Cluster mode)
   - Google Memorystore Redis (HA)
   - Azure Cache for Redis (Premium tier)

3. **Load Balancer**:
   - AWS ALB + CloudFront
   - Google Cloud Load Balancing
   - Azure Application Gateway

4. **Container Orchestration**:
   - Kubernetes (EKS, GKE, AKS)
   - AWS ECS Fargate
   - Google Cloud Run

---

## 🔧 Migration Path: Staging → Production

### Phase 1: Database Migration
```bash
# Migrate to managed PostgreSQL
pg_dump -h localhost -U acs_user acs_production > backup.sql
psql -h production-rds-endpoint.aws.com -U admin acs_production < backup.sql
```

### Phase 2: Redis Migration
```bash
# Enable Redis persistence
# Migrate to ElastiCache/Memorystore cluster
```

### Phase 3: Kubernetes Deployment
```bash
# Convert docker-compose to Kubernetes manifests
kompose convert

# Apply to cluster
kubectl apply -f k8s/
```

---

## 📊 Production Checklist

Prima di andare in production con 100K+ dispositivi:

### Infrastructure:
- [ ] Database: Multi-AZ replication configurata
- [ ] Redis: Cluster/Sentinel mode attivo
- [ ] Load Balancer: Health checks configurati
- [ ] Auto-scaling: Policy configurate (CPU > 70%)
- [ ] Backup: Automated daily backups attivi
- [ ] Disaster Recovery: Runbook testato
- [ ] SSL/TLS: Valid certificates (Let's Encrypt / ACM)

### Monitoring:
- [ ] Prometheus/Grafana: Metrics collection attiva
- [ ] ELK Stack / CloudWatch: Log aggregation
- [ ] APM: Distributed tracing (Jaeger / X-Ray)
- [ ] Uptime monitoring: StatusPage / PingDom
- [ ] Alerting: PagerDuty / OpsGenie integrato

### Security:
- [ ] WAF: DDoS protection attiva
- [ ] Secrets: Vault / AWS Secrets Manager
- [ ] Network: Private subnets + NAT Gateway
- [ ] Firewall: Security groups / Network ACLs
- [ ] Audit: CloudTrail / Audit logs enabled
- [ ] Penetration Testing: Completato

### Performance:
- [ ] Load Testing: 100K concurrent connections testato
- [ ] Database: Query optimization + indexes
- [ ] CDN: Static assets serviti da CloudFront
- [ ] Caching: Multi-tier strategy attiva
- [ ] Connection Pooling: PgBouncer configurato

---

## 🚀 Quick Start Guide

### Staging Deployment (Current Setup):
```bash
# Deploy to single server (OK for testing)
./deploy.sh production
```

### Production Deployment (Kubernetes):
```bash
# Build and push images
docker build -t registry.example.com/acs:latest .
docker push registry.example.com/acs:latest

# Deploy to K8s
kubectl apply -f k8s/

# Scale horizontally
kubectl scale deployment acs-app --replicas=5
```

---

## 🔍 Monitoring Endpoints

Per production, assicurati di monitorare:

- `/health` - Basic health check
- `/api/v1/system/health` - Detailed system status
- `/api/v1/telemetry/current` - Real-time metrics
- `/api/v1/stomp/metrics` - STOMP broker metrics

---

## 📞 Support & Escalation

**Prima di deployment production:**

1. Review architettura con team infrastructure
2. Load testing completato (K6/JMeter)
3. Security audit sign-off
4. Disaster recovery plan approvato
5. On-call rotation configurata

---

**Questa configurazione base è adatta per:**
- ✅ Development
- ✅ Testing
- ✅ Staging
- ✅ Single-server deployments (<10K devices)

**NON adatta per:**
- ❌ Production carrier-grade (100K+ devices)
- ❌ Mission-critical workloads
- ❌ SLA > 99.9% uptime requirements

Per production carrier-grade, contatta il team DevOps per una review dell'architettura.
