# ACS Kubernetes Deployment Guide

## Overview

This guide provides comprehensive instructions for deploying ACS (Auto Configuration Server) on Kubernetes with carrier-grade high availability for managing 100,000+ CPE devices.

## Architecture

### Carrier-Grade Production Architecture (100K+ devices)

**⚠️ CRITICAL: For production use, you MUST use managed services for PostgreSQL and Redis.**

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer                        │
│            (Ingress + TR-069 LoadBalancer)              │
└───────────┬─────────────────────┬───────────────────────┘
            │                     │
    ┌───────▼────────┐   ┌────────▼────────┐
    │   Ingress      │   │  TR-069 Service │
    │  Controller    │   │  (Port 7547)    │
    └───────┬────────┘   └────────┬────────┘
            │                     │
    ┌───────▼─────────────────────▼──────┐
    │         ACS App Pods (3-20)        │
    │    (PHP-FPM + Nginx + Laravel)     │
    │      Auto-scaling enabled          │
    └───────┬────────────────────────────┘
            │
    ┌───────▼────────────────────────────┐
    │    Queue Workers (5-50 Pods)       │
    │      Auto-scaling enabled          │
    └───────┬────────────────────────────┘
            │
    ┌───────┴────────┬──────────┬────────┐
    │                │          │        │
┌───▼────────┐ ┌────▼──────┐ ┌─▼──────┐ ┌──────┐
│ AWS RDS    │ │ElastiCache│ │Prosody │ │ PVC  │
│PostgreSQL  │ │  Redis    │ │ XMPP   │ │Storage│
│(Multi-AZ)  │ │ (Cluster) │ │(2 pods)│ │       │
└────────────┘ └───────────┘ └────────┘ └──────┘
```

### Development/Staging Architecture

For development and staging environments, the Helm chart includes optional embedded PostgreSQL and Redis StatefulSets. **These are NOT suitable for production carrier-grade deployments.**

```
Same as above, but with:
┌────────────┐ ┌────────────┐
│PostgreSQL  │ │   Redis    │
│StatefulSet │ │StatefulSet │
│(1 replica) │ │(1 replica) │
└────────────┘ └────────────┘
```

### Resource Allocation

#### Production Configuration (100K+ devices)

| Component | Replicas | CPU Request | CPU Limit | Memory Request | Memory Limit |
|-----------|----------|-------------|-----------|----------------|--------------|
| ACS App | 3-20 (HPA) | 1 CPU | 2 CPU | 2 GB | 4 GB |
| Workers | 5-50 (HPA) | 500m | 1 CPU | 1 GB | 2 GB |
| PostgreSQL | 3 | 2 CPU | 4 CPU | 8 GB | 16 GB |
| Redis | 3 | 500m | 1 CPU | 2 GB | 4 GB |
| Prosody | 2 | 250m | 500m | 512 MB | 1 GB |

**Total Minimum Resources:**
- CPU: ~14 cores
- Memory: ~42 GB
- Storage: ~650 GB (PostgreSQL 500GB + Redis 50GB + App 100GB)

## Prerequisites

### Required Tools

- **Kubernetes Cluster**: v1.24+
- **kubectl**: Latest version
- **Helm**: v3.10+
- **Storage Class**: Fast SSD storage (e.g., gp3, premium-ssd)

### Optional Tools

- **cert-manager**: For automatic SSL/TLS certificates
- **nginx-ingress-controller**: For ingress management
- **Prometheus + Grafana**: For monitoring
- **EFK Stack**: For centralized logging

### Cloud Provider Requirements

#### AWS
```bash
# EKS cluster with:
- 3+ worker nodes (c5.2xlarge or equivalent)
- EBS CSI driver for storage
- NLB for TR-069 LoadBalancer
- ACM for SSL certificates
```

#### GCP
```bash
# GKE cluster with:
- 3+ nodes (n2-standard-4 or equivalent)
- GCE Persistent Disk CSI driver
- Cloud Load Balancer
- Google-managed certificates
```

#### Azure
```bash
# AKS cluster with:
- 3+ nodes (Standard_D4s_v3 or equivalent)
- Azure Disk CSI driver
- Azure Load Balancer
- Azure Key Vault for secrets
```

## Installation

### 1. Prepare Kubernetes Cluster

```bash
# Create namespace
kubectl create namespace acs-production

# Verify cluster resources
kubectl top nodes
```

### 2. Configure Storage Classes

Ensure you have a fast SSD storage class:

```bash
# Check available storage classes
kubectl get storageclass

# Example for AWS EBS gp3
cat <<EOF | kubectl apply -f -
apiVersion: storage.k8s.io/v1
kind: StorageClass
metadata:
  name: fast-ssd
provisioner: ebs.csi.aws.com
parameters:
  type: gp3
  iops: "3000"
  throughput: "125"
volumeBindingMode: WaitForFirstConsumer
allowVolumeExpansion: true
EOF
```

### 3. Create Secrets

Create the `acs-secrets` secret with all required credentials:

```bash
# Generate passwords
POSTGRES_PASSWORD=$(openssl rand -base64 32)
REDIS_PASSWORD=$(openssl rand -base64 32)
APP_KEY=$(openssl rand -base64 32)

# Create secret
kubectl create secret generic acs-secrets \
  --from-literal=postgres-password="${POSTGRES_PASSWORD}" \
  --from-literal=postgres-user-password="${POSTGRES_PASSWORD}" \
  --from-literal=redis-password="${REDIS_PASSWORD}" \
  --from-literal=app-key="base64:${APP_KEY}" \
  --from-literal=openai-api-key="sk-your-key-here" \
  --namespace acs-production
```

### 4. Customize Values

Edit `k8s/helm/acs/values.yaml`:

```yaml
# Update domain
global:
  domain: acs.yourcompany.com

# Update image repository
image:
  app:
    repository: your-registry.io/acs
    tag: "1.0.0"

# Configure external database (optional)
postgresql:
  enabled: false  # Disable if using managed PostgreSQL

externalDatabase:
  enabled: true
  host: your-postgres.rds.amazonaws.com
  port: 5432
  database: acs
  username: acs_user

# Configure external Redis (optional)
redis:
  enabled: false  # Disable if using managed Redis

externalRedis:
  enabled: true
  host: your-redis.cache.amazonaws.com
  port: 6379
```

### 5. Deploy with Helm

#### Quick Install

```bash
cd k8s
./deploy-k8s.sh install
```

#### Manual Install

```bash
cd k8s/helm/acs

helm install acs . \
  --namespace acs-production \
  --create-namespace \
  --timeout 10m \
  --wait \
  --values values.yaml
```

#### Custom Install

```bash
helm install acs . \
  --namespace acs-production \
  --set replicaCount.app=5 \
  --set replicaCount.worker=10 \
  --set postgresql.enabled=false \
  --set externalDatabase.enabled=true \
  --values custom-values.yaml
```

### 6. Verify Deployment

```bash
# Check pods
kubectl get pods -n acs-production

# Check services
kubectl get svc -n acs-production

# Check ingress
kubectl get ingress -n acs-production

# Verify all pods are running
kubectl wait --for=condition=ready pod \
  -l app.kubernetes.io/instance=acs \
  -n acs-production \
  --timeout=5m
```

## Configuration

### Auto-Scaling Configuration

#### App HPA

```yaml
app:
  autoscaling:
    enabled: true
    minReplicas: 3
    maxReplicas: 20
    targetCPUUtilizationPercentage: 70
    targetMemoryUtilizationPercentage: 80
```

#### Worker HPA

```yaml
worker:
  autoscaling:
    enabled: true
    minReplicas: 5
    maxReplicas: 50
    targetCPUUtilizationPercentage: 75
```

### TLS/SSL Configuration

#### Using cert-manager

```yaml
ingress:
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
  tls:
    - secretName: acs-tls-cert
      hosts:
        - acs.yourcompany.com
```

#### Using pre-existing certificate

```bash
# Create TLS secret
kubectl create secret tls acs-tls-cert \
  --cert=path/to/tls.crt \
  --key=path/to/tls.key \
  --namespace acs-production
```

### Database Configuration

#### Managed PostgreSQL (REQUIRED for Production)

**⚠️ For carrier-grade production with 100K+ devices, you MUST use managed PostgreSQL.**

The embedded PostgreSQL StatefulSet lacks:
- Automatic failover
- Leader election & replication
- Point-in-time recovery
- Automated backups
- Split-brain prevention

Supported managed services:

```yaml
postgresql:
  enabled: false

externalDatabase:
  enabled: true
  host: "postgres.us-east-1.rds.amazonaws.com"
  port: 5432
  database: "acs"
  username: "acs_user"
  existingSecret: "acs-external-db"
```

#### AWS RDS PostgreSQL (Recommended)

```yaml
postgresql:
  enabled: false

externalDatabase:
  enabled: true
  host: "acs-db.abc123.us-east-1.rds.amazonaws.com"
  port: 5432
  database: "acs"
  username: "acs_user"
  existingSecret: "acs-secrets"
```

#### Self-Hosted PostgreSQL with Patroni/CrunchyData (Advanced)

If you cannot use managed services, deploy with a production-grade operator:

```bash
# Install CrunchyData PostgreSQL Operator
kubectl apply -k github.com/CrunchyData/postgres-operator-examples/kustomize/install

# Create PostgreSQL cluster
kubectl apply -f - <<EOF
apiVersion: postgres-operator.crunchydata.com/v1beta1
kind: PostgresCluster
metadata:
  name: acs-postgres
  namespace: acs-production
spec:
  postgresVersion: 16
  instances:
    - name: instance1
      replicas: 3
      dataVolumeClaimSpec:
        accessModes:
        - "ReadWriteOnce"
        resources:
          requests:
            storage: 500Gi
EOF
```

### Redis Configuration

#### Managed Redis (REQUIRED for Production)

**⚠️ For carrier-grade production with 100K+ devices, you MUST use managed Redis.**

The embedded Redis StatefulSet lacks:
- Automatic failover
- Master/replica replication
- Sentinel monitoring
- Cluster mode
- Split-brain prevention

Supported managed services:

```yaml
redis:
  enabled: false

externalRedis:
  enabled: true
  host: "redis.cache.amazonaws.com"
  port: 6379
  existingSecret: "acs-external-redis"
```

#### AWS ElastiCache Redis (Recommended)

```yaml
redis:
  enabled: false

externalRedis:
  enabled: true
  host: "acs-redis.abc123.use1.cache.amazonaws.com"
  port: 6379
  existingSecret: "acs-secrets"
```

#### Self-Hosted Redis with Sentinel/Enterprise (Advanced)

If you cannot use managed services:

```bash
# Install Redis Enterprise Operator
kubectl apply -f https://raw.githubusercontent.com/RedisLabs/redis-enterprise-k8s-docs/master/bundle.yaml

# Create Redis Enterprise cluster
kubectl apply -f - <<EOF
apiVersion: app.redislabs.com/v1
kind: RedisEnterpriseCluster
metadata:
  name: acs-redis
  namespace: acs-production
spec:
  nodes: 3
  redisEnterpriseNodeResources:
    limits:
      cpu: "1000m"
      memory: 4Gi
    requests:
      cpu: "500m"
      memory: 2Gi
EOF
```

## Operations

### Monitoring

#### Install Prometheus + Grafana

```bash
# Add Prometheus Helm repo
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts

# Install kube-prometheus-stack
helm install monitoring prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  --create-namespace
```

#### Enable ServiceMonitor

```yaml
monitoring:
  enabled: true
  serviceMonitor:
    enabled: true
    interval: 30s
```

### Logging

#### View Logs

```bash
# App logs
./deploy-k8s.sh logs app

# Worker logs
kubectl logs -l app.kubernetes.io/component=worker -n acs-production -f

# All logs
kubectl logs -l app.kubernetes.io/instance=acs -n acs-production --all-containers
```

### Scaling

#### Manual Scaling

```bash
# Scale app pods
./deploy-k8s.sh scale app 10

# Scale workers
kubectl scale deployment acs-worker -n acs-production --replicas=20
```

#### Auto-Scaling

HPA automatically scales based on CPU/Memory:

```bash
# Check HPA status
kubectl get hpa -n acs-production

# Describe HPA
kubectl describe hpa acs-app -n acs-production
```

### Updates & Rollbacks

#### Upgrade Deployment

```bash
# Update image version
./deploy-k8s.sh upgrade --set image.app.tag=1.1.0

# Or with Helm
helm upgrade acs ./k8s/helm/acs \
  --namespace acs-production \
  --set image.app.tag=1.1.0
```

#### Rollback

```bash
# List releases
helm history acs -n acs-production

# Rollback to previous version
./deploy-k8s.sh rollback

# Rollback to specific revision
helm rollback acs 2 -n acs-production
```

### Backup & Restore

#### Database Backup

```bash
# Backup PostgreSQL
kubectl exec -it acs-postgresql-0 -n acs-production -- \
  pg_dump -U acs_user acs > backup-$(date +%Y%m%d).sql

# Restore
kubectl exec -i acs-postgresql-0 -n acs-production -- \
  psql -U acs_user acs < backup-20241023.sql
```

#### Persistent Volume Backup

```bash
# Create VolumeSnapshot (requires CSI driver support)
cat <<EOF | kubectl apply -f -
apiVersion: snapshot.storage.k8s.io/v1
kind: VolumeSnapshot
metadata:
  name: acs-storage-snapshot
  namespace: acs-production
spec:
  volumeSnapshotClassName: csi-snapclass
  source:
    persistentVolumeClaimName: acs-storage
EOF
```

## Troubleshooting

### Pods Not Starting

```bash
# Check pod status
kubectl describe pod <pod-name> -n acs-production

# Check events
kubectl get events -n acs-production --sort-by='.lastTimestamp'

# Check logs
kubectl logs <pod-name> -n acs-production --all-containers
```

### Database Connection Issues

```bash
# Test PostgreSQL connectivity
kubectl run -it --rm debug --image=postgres:16 --restart=Never -n acs-production -- \
  psql -h acs-postgresql -U acs_user -d acs

# Check PostgreSQL service
kubectl get svc acs-postgresql -n acs-production
```

### Redis Connection Issues

```bash
# Test Redis connectivity
kubectl run -it --rm debug --image=redis:7 --restart=Never -n acs-production -- \
  redis-cli -h acs-redis -a <password> ping
```

### Storage Issues

```bash
# Check PVCs
kubectl get pvc -n acs-production

# Check PVs
kubectl get pv

# Describe PVC
kubectl describe pvc acs-storage -n acs-production
```

### Network Policy Issues

```bash
# Temporarily disable network policies
kubectl delete networkpolicy --all -n acs-production

# Re-apply after testing
helm upgrade acs ./k8s/helm/acs -n acs-production
```

## Performance Tuning

### PostgreSQL Optimization

Adjust in `values.yaml`:

```yaml
postgresql:
  primary:
    configuration: |
      max_connections = 500
      shared_buffers = 4GB
      effective_cache_size = 12GB
      work_mem = 8MB
      maintenance_work_mem = 1GB
```

### PHP-FPM Tuning

Adjust in ConfigMap:

```yaml
configData:
  php.ini: |
    memory_limit = 512M
    opcache.memory_consumption = 256
    opcache.max_accelerated_files = 20000
```

### Resource Limits

Fine-tune based on load:

```yaml
app:
  resources:
    requests:
      cpu: "2000m"    # Increase for better performance
      memory: "4Gi"
    limits:
      cpu: "4000m"
      memory: "8Gi"
```

## Security Best Practices

1. **Use Network Policies**: Enabled by default, restricts pod-to-pod communication
2. **Enable Pod Security Standards**: Use PSS restricted policy
3. **Rotate Secrets Regularly**: Update database/Redis passwords periodically
4. **Use Private Registry**: Store images in private container registry
5. **Enable RBAC**: Restrict service account permissions
6. **TLS Everywhere**: Enable TLS for all external and internal communication
7. **Scan Images**: Use tools like Trivy for vulnerability scanning
8. **Audit Logging**: Enable Kubernetes audit logs

## Production Checklist

- [ ] Kubernetes cluster with 3+ nodes across multiple AZs
- [ ] Fast SSD storage class configured
- [ ] All secrets created and secured
- [ ] External managed database configured (or Patroni)
- [ ] External managed Redis configured (or Sentinel)
- [ ] cert-manager installed for TLS
- [ ] Ingress controller configured
- [ ] Monitoring (Prometheus + Grafana) deployed
- [ ] Centralized logging (EFK) configured
- [ ] Backup strategy implemented
- [ ] Resource limits properly set
- [ ] HPA enabled and tested
- [ ] Network policies enabled
- [ ] Pod security policies applied
- [ ] Load testing completed (100K+ concurrent connections)

## Cost Optimization

### Use Spot/Preemptible Instances

```yaml
nodeSelector:
  node.kubernetes.io/instance-type: "spot"

tolerations:
  - key: "node.kubernetes.io/spot"
    operator: "Exists"
    effect: "NoSchedule"
```

### Managed Services

Use cloud provider managed services for:
- PostgreSQL (RDS, Cloud SQL, Azure Database)
- Redis (ElastiCache, MemoryStore, Azure Cache)
- Load Balancer (ALB, Cloud Load Balancer, Azure LB)

### Right-Sizing

Monitor actual resource usage and adjust:

```bash
# Check actual resource usage
kubectl top pods -n acs-production
kubectl top nodes

# Adjust resources based on metrics
```

## Support

For issues or questions:
- Check logs: `./deploy-k8s.sh logs <component>`
- Review events: `kubectl get events -n acs-production`
- Check pod status: `kubectl get pods -n acs-production`
- Consult documentation: [DEPLOYMENT.md](DEPLOYMENT.md)

## References

- [Helm Documentation](https://helm.sh/docs/)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [PostgreSQL Kubernetes Operators](https://github.com/zalando/postgres-operator)
- [Redis Kubernetes Operators](https://github.com/spotahome/redis-operator)
