# Redis Cluster Production Guide
**Carrier-Grade High Availability for ACS**

## Overview

This guide covers Redis cluster configuration for production ACS deployments managing 100,000+ CPE devices. Redis is used for:

- **Queue Processing** (Laravel Horizon) - TR-069/TR-369 requests, provisioning jobs
- **Cache** - Device data, parameter caching, tenant scoping
- **Session Storage** - Multi-tenant session isolation
- **WebSocket Routing** (Laravel Reverb) - Real-time alarm broadcasting
- **Prometheus Metrics** - Time-series metrics storage

## Architecture Options

### Option 1: Redis Cluster (Recommended for 100K+ devices)

Native Redis Cluster provides horizontal scaling with automatic sharding.

```
┌─────────────────────────────────────────────────────────────┐
│                    Redis Cluster (6 nodes)                   │
├─────────────────────────────────────────────────────────────┤
│  Master 1 (slots 0-5460)  ←→  Replica 1                     │
│  Master 2 (slots 5461-10922) ←→  Replica 2                  │
│  Master 3 (slots 10923-16383) ←→  Replica 3                 │
└─────────────────────────────────────────────────────────────┘
```

**Minimum Requirements:**
- 6 nodes (3 masters + 3 replicas)
- 4GB RAM per node minimum
- Low-latency network (<1ms between nodes)

### Option 2: Redis Sentinel (Simpler HA)

Sentinel provides automatic failover for master-replica setups.

```
┌─────────────────────────────────────────────────────────────┐
│                    Redis Sentinel                            │
├─────────────────────────────────────────────────────────────┤
│  Sentinel 1 ────┐                                           │
│  Sentinel 2 ────┼──→ Master ←──→ Replica 1                  │
│  Sentinel 3 ────┘              ←──→ Replica 2               │
└─────────────────────────────────────────────────────────────┘
```

**Best for:**
- Smaller deployments (<50K devices)
- When sharding is not required
- Simpler operational overhead

### Option 3: Cloud-Managed Redis

| Provider | Service | Cluster Support | TLS |
|----------|---------|-----------------|-----|
| AWS | ElastiCache | Yes | Yes |
| GCP | Cloud Memorystore | Yes | Yes |
| Azure | Azure Cache | Yes | Yes |
| DigitalOcean | Managed Redis | Sentinel | Yes |

## Configuration

### Development (Single Instance)

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CLUSTER_ENABLED=false
```

### Production - Redis Cluster

```env
REDIS_CLIENT=phpredis
REDIS_PASSWORD=your-secure-password

# Enable cluster mode
REDIS_CLUSTER_ENABLED=true

# 6-node cluster (3 masters + 3 replicas)
REDIS_CLUSTER_NODE_1=redis-node-1.production.local
REDIS_CLUSTER_PORT_1=6379
REDIS_CLUSTER_NODE_2=redis-node-2.production.local
REDIS_CLUSTER_PORT_2=6379
REDIS_CLUSTER_NODE_3=redis-node-3.production.local
REDIS_CLUSTER_PORT_3=6379
REDIS_CLUSTER_NODE_4=redis-node-4.production.local
REDIS_CLUSTER_PORT_4=6379
REDIS_CLUSTER_NODE_5=redis-node-5.production.local
REDIS_CLUSTER_PORT_5=6379
REDIS_CLUSTER_NODE_6=redis-node-6.production.local
REDIS_CLUSTER_PORT_6=6379

# TLS for encrypted connections
REDIS_TLS=true
REDIS_TLS_VERIFY=true

# Timeouts
REDIS_TIMEOUT=5
REDIS_READ_TIMEOUT=60
REDIS_MAX_RETRIES=3
```

### Production - Redis Sentinel

```env
REDIS_CLIENT=phpredis
REDIS_PASSWORD=your-secure-password

# Enable Sentinel mode
REDIS_SENTINEL_ENABLED=true
REDIS_SENTINEL_1=sentinel-1.production.local:26379
REDIS_SENTINEL_2=sentinel-2.production.local:26379
REDIS_SENTINEL_3=sentinel-3.production.local:26379
REDIS_SENTINEL_SERVICE=mymaster
```

### Cloud-Managed (AWS ElastiCache Example)

```env
REDIS_CLIENT=phpredis
REDIS_HOST=acs-redis.abc123.clustercfg.usw2.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=your-auth-token
REDIS_TLS=true
REDIS_TLS_VERIFY=true
REDIS_CLUSTER_ENABLED=true
```

## Database Separation

ACS uses separate Redis databases for isolation:

| Database | Purpose | Key Prefix |
|----------|---------|------------|
| 0 | Default/General | `acs-database-` |
| 1 | Cache | `acs-cache-` |
| 2 | Sessions | `acs-session-` |
| 3 | Prometheus Metrics | `acs-metrics-` |
| 4 | Queue (Horizon) | `acs-queue-` |
| 5 | Broadcasting (Reverb) | `acs-broadcast-` |

**Note:** Redis Cluster does not support `SELECT` database commands. All data is stored in database 0 with key prefixing.

## Kubernetes Deployment

### Redis Cluster with Bitnami Helm Chart

```yaml
# values-redis.yaml
architecture: replication
auth:
  enabled: true
  password: "your-secure-password"

master:
  resources:
    requests:
      memory: "4Gi"
      cpu: "2"
    limits:
      memory: "8Gi"
      cpu: "4"
  persistence:
    enabled: true
    size: 50Gi
    storageClass: fast-ssd

replica:
  replicaCount: 3
  resources:
    requests:
      memory: "4Gi"
      cpu: "2"

sentinel:
  enabled: true
  masterSet: mymaster
  
metrics:
  enabled: true
  serviceMonitor:
    enabled: true
```

```bash
helm install acs-redis bitnami/redis \
  --namespace acs \
  -f values-redis.yaml
```

### Redis Cluster Operator (Production)

For true Redis Cluster with sharding:

```yaml
apiVersion: redis.redis.opstreelabs.in/v1beta1
kind: RedisCluster
metadata:
  name: acs-redis-cluster
spec:
  clusterSize: 3
  clusterVersion: v7
  redisSecret:
    name: redis-secret
    key: password
  leader:
    replicas: 3
    resources:
      requests:
        cpu: "2"
        memory: "4Gi"
  follower:
    replicas: 3
    resources:
      requests:
        cpu: "1"
        memory: "2Gi"
  storage:
    volumeClaimTemplate:
      spec:
        accessModes: ["ReadWriteOnce"]
        resources:
          requests:
            storage: 50Gi
```

## Performance Tuning

### Redis Configuration (`redis.conf`)

```conf
# Memory
maxmemory 4gb
maxmemory-policy allkeys-lru

# Persistence (for recovery)
appendonly yes
appendfsync everysec

# Cluster
cluster-enabled yes
cluster-config-file nodes.conf
cluster-node-timeout 5000

# Performance
tcp-keepalive 300
timeout 0

# Connections
maxclients 10000
```

### Laravel Horizon Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'tr069', 'tr369', 'provisioning', 'default'],
            'balance' => 'auto',
            'processes' => 20,
            'tries' => 3,
            'timeout' => 120,
        ],
    ],
],
```

## Monitoring

### Prometheus Metrics

The ACS exports Redis metrics via `/metrics` endpoint:

```
# Redis connection status
acs_redis_connected{connection="default"} 1

# Queue metrics
acs_queue_jobs_processed_total{queue="tr069"} 12345
acs_queue_jobs_failed_total{queue="tr069"} 23
acs_queue_wait_seconds{queue="tr069"} 0.5
```

### Grafana Dashboard

Import the provided dashboard from `deploy/grafana/redis-dashboard.json` for:
- Memory usage and evictions
- Connection counts
- Command latency
- Cluster health
- Replication lag

### Health Check Endpoint

```bash
curl http://localhost:5000/api/health | jq '.redis'
```

```json
{
  "redis": {
    "status": "healthy",
    "connection": "ok",
    "latency_ms": 0.5,
    "memory_used": "1.2GB",
    "connected_clients": 45
  }
}
```

## Failover Testing

### Simulate Master Failure

```bash
# Identify current master
redis-cli -c -h redis-node-1 cluster nodes | grep master

# Force failover
redis-cli -c -h redis-node-1 DEBUG SEGFAULT
```

### Verify Failover

```bash
# Check new master
redis-cli -c -h redis-node-2 cluster nodes | grep master

# Verify ACS reconnection
php artisan tinker --execute="Redis::ping()"
```

## Troubleshooting

### Connection Issues

```bash
# Test connectivity
redis-cli -h redis-host -p 6379 -a password ping

# Check cluster status
redis-cli -c cluster info
redis-cli -c cluster nodes
```

### Memory Issues

```bash
# Check memory usage
redis-cli info memory

# Analyze large keys
redis-cli --bigkeys
```

### Slow Commands

```bash
# Enable slowlog
redis-cli config set slowlog-log-slower-than 10000

# View slow commands
redis-cli slowlog get 10
```

## Security Best Practices

1. **Authentication**: Always set `REDIS_PASSWORD` in production
2. **TLS**: Enable `REDIS_TLS=true` for encrypted connections
3. **Network**: Use private VPC/subnet for Redis nodes
4. **Firewall**: Only allow connections from application servers
5. **Rename Commands**: Disable dangerous commands in production

```conf
# redis.conf
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG "ACS_CONFIG_7f8a9b2c"
```

## Capacity Planning

| Devices | Redis Memory | Nodes | Connections |
|---------|--------------|-------|-------------|
| 10,000 | 2GB | 1 master + 2 replicas | 100 |
| 50,000 | 8GB | 3 masters + 3 replicas | 500 |
| 100,000 | 16GB | 3 masters + 3 replicas | 1,000 |
| 500,000 | 64GB | 6 masters + 6 replicas | 5,000 |

**Memory per device (estimated):**
- Device cache: ~500 bytes
- Session: ~2KB (if user logged in)
- Queue jobs: ~1KB per pending job
- Metrics: ~100 bytes per metric

## Related Documentation

- [Multi-Tenant Architecture](./MULTI_TENANT_AUTH_ROADMAP.md)
- [WebSocket Integration](./WEBSOCKET_MOBILE_INTEGRATION.md)
- [Kubernetes Deployment](./KUBERNETES_DEPLOYMENT.md)
- [Security Hardening](./SECURITY_HARDENING.md)
