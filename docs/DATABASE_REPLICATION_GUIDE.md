# PostgreSQL Replication Guide
**Carrier-Grade High Availability for ACS**

## Overview

This guide covers PostgreSQL read replica configuration for production ACS deployments managing 100,000+ CPE devices. Database replication provides:

- **Read Scalability** - Distribute SELECT queries across replicas
- **High Availability** - Failover capability if primary fails
- **Geographic Distribution** - Place replicas closer to users
- **Backup Isolation** - Run backups on replicas without impacting primary

## Architecture

### Read/Write Splitting

```
┌─────────────────────────────────────────────────────────────┐
│                        ACS Application                       │
├─────────────────────────────────────────────────────────────┤
│                    Laravel Database Layer                    │
│                                                             │
│   WRITE (INSERT/UPDATE/DELETE)     READ (SELECT)            │
│            │                           │                    │
│            ▼                           ▼                    │
│   ┌─────────────┐           ┌─────────────────────┐         │
│   │   Primary   │──────────▶│  Read Replicas (3)  │         │
│   │  (Master)   │  Streaming│  ┌───┐ ┌───┐ ┌───┐  │         │
│   │             │  Replication│  │ R1│ │ R2│ │ R3│  │         │
│   └─────────────┘           │  └───┘ └───┘ └───┘  │         │
│                             └─────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

### Sticky Sessions

When `DB_STICKY=true` (default), after a write operation, subsequent reads in the same request are routed to the primary database. This prevents reading stale data immediately after writes.

```php
// Write goes to primary
$device = CpeDevice::create(['serial_number' => 'ABC123']);

// With sticky=true, this read also goes to primary
$device->refresh(); // Gets fresh data, not stale replica data
```

## Configuration

### Development (Single Instance)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=acs_database
DB_USERNAME=acs_user
DB_PASSWORD=your-password
DB_READ_REPLICA_ENABLED=false
```

### Production (Read Replicas)

```env
DB_CONNECTION=pgsql
DB_HOST=primary.db.production.local
DB_PORT=5432
DB_DATABASE=acs_database
DB_USERNAME=acs_user
DB_PASSWORD=your-secure-password
DB_SSLMODE=require

# Enable read replicas
DB_READ_REPLICA_ENABLED=true

# Up to 3 read replicas (load-balanced automatically)
DB_READ_HOST_1=replica-1.db.production.local
DB_READ_HOST_2=replica-2.db.production.local
DB_READ_HOST_3=replica-3.db.production.local
DB_READ_PORT=5432

# Sticky sessions (recommended)
DB_STICKY=true
```

### Cloud-Managed PostgreSQL

#### AWS RDS

```env
DB_HOST=acs-primary.abc123.us-west-2.rds.amazonaws.com
DB_READ_REPLICA_ENABLED=true
DB_READ_HOST_1=acs-replica-1.abc123.us-west-2.rds.amazonaws.com
DB_READ_HOST_2=acs-replica-2.abc123.us-west-2.rds.amazonaws.com
DB_SSLMODE=require
```

#### Google Cloud SQL

```env
DB_HOST=/cloudsql/project:region:primary-instance
DB_READ_REPLICA_ENABLED=true
DB_READ_HOST_1=/cloudsql/project:region:replica-1
DB_READ_HOST_2=/cloudsql/project:region:replica-2
```

#### Azure Database for PostgreSQL

```env
DB_HOST=acs-primary.postgres.database.azure.com
DB_READ_REPLICA_ENABLED=true
DB_READ_HOST_1=acs-replica-1.postgres.database.azure.com
DB_READ_HOST_2=acs-replica-2.postgres.database.azure.com
DB_SSLMODE=require
```

#### Neon (Serverless)

```env
# Neon provides read replicas via connection pooling
DB_HOST=ep-cool-darkness-123456.us-east-2.aws.neon.tech
DB_READ_REPLICA_ENABLED=true
DB_READ_HOST_1=ep-cool-darkness-123456-pooler.us-east-2.aws.neon.tech
```

## Kubernetes Deployment

### PostgreSQL with CloudNativePG Operator

```yaml
apiVersion: postgresql.cnpg.io/v1
kind: Cluster
metadata:
  name: acs-postgres
  namespace: acs
spec:
  instances: 3
  primaryUpdateStrategy: unsupervised
  
  postgresql:
    parameters:
      max_connections: "1000"
      shared_buffers: "2GB"
      effective_cache_size: "6GB"
      work_mem: "16MB"
      maintenance_work_mem: "512MB"
      wal_level: "replica"
      max_wal_senders: "10"
      max_replication_slots: "10"
      hot_standby: "on"
  
  storage:
    size: 100Gi
    storageClass: fast-ssd
  
  resources:
    requests:
      memory: "4Gi"
      cpu: "2"
    limits:
      memory: "8Gi"
      cpu: "4"
  
  backup:
    barmanObjectStore:
      destinationPath: "s3://acs-backups/postgres"
      s3Credentials:
        accessKeyId:
          name: aws-creds
          key: ACCESS_KEY_ID
        secretAccessKey:
          name: aws-creds
          key: SECRET_ACCESS_KEY
    retentionPolicy: "30d"
  
  monitoring:
    enablePodMonitor: true
```

### Service Configuration

```yaml
apiVersion: v1
kind: Service
metadata:
  name: acs-postgres-rw
spec:
  selector:
    cnpg.io/cluster: acs-postgres
    role: primary
  ports:
    - port: 5432
---
apiVersion: v1
kind: Service
metadata:
  name: acs-postgres-ro
spec:
  selector:
    cnpg.io/cluster: acs-postgres
    role: replica
  ports:
    - port: 5432
```

### Application ConfigMap

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: acs-db-config
data:
  DB_CONNECTION: "pgsql"
  DB_HOST: "acs-postgres-rw"
  DB_READ_REPLICA_ENABLED: "true"
  DB_READ_HOST_1: "acs-postgres-ro"
  DB_STICKY: "true"
```

## Manual PostgreSQL Setup

### Primary Server Configuration

```bash
# postgresql.conf
listen_addresses = '*'
max_connections = 1000
shared_buffers = 2GB
wal_level = replica
max_wal_senders = 10
max_replication_slots = 10
hot_standby = on
synchronous_commit = on
```

```bash
# pg_hba.conf - Allow replication connections
host    replication     replicator      10.0.0.0/8      scram-sha-256
```

### Create Replication User

```sql
CREATE USER replicator WITH REPLICATION ENCRYPTED PASSWORD 'secure-password';
```

### Replica Setup

```bash
# Stop PostgreSQL on replica
sudo systemctl stop postgresql

# Clear data directory
sudo rm -rf /var/lib/postgresql/16/main/*

# Base backup from primary
pg_basebackup -h primary.db.local -D /var/lib/postgresql/16/main -U replicator -P -Xs -R

# Start replica
sudo systemctl start postgresql
```

### Verify Replication

```sql
-- On primary
SELECT client_addr, state, sent_lsn, write_lsn, flush_lsn, replay_lsn 
FROM pg_stat_replication;

-- On replica
SELECT pg_is_in_recovery(), pg_last_wal_receive_lsn(), pg_last_wal_replay_lsn();
```

## Monitoring

### Health Check Endpoint

```bash
curl http://localhost:5000/api/health | jq '.database'
```

```json
{
  "database": {
    "status": "healthy",
    "primary": {
      "connection": "ok",
      "latency_ms": 1.2
    },
    "replicas": [
      {"host": "replica-1", "status": "ok", "lag_bytes": 0},
      {"host": "replica-2", "status": "ok", "lag_bytes": 1024},
      {"host": "replica-3", "status": "ok", "lag_bytes": 512}
    ]
  }
}
```

### Prometheus Metrics

```
# Connection pool stats
acs_db_connections_active{role="primary"} 45
acs_db_connections_active{role="replica"} 120

# Replication lag
acs_db_replication_lag_bytes{replica="replica-1"} 0
acs_db_replication_lag_bytes{replica="replica-2"} 1024

# Query distribution
acs_db_queries_total{role="primary",type="write"} 5000
acs_db_queries_total{role="replica",type="read"} 50000
```

### Grafana Dashboard

Monitor these key metrics:
- Replication lag (bytes and time)
- Connection pool utilization
- Query latency by role
- Transaction rates
- Buffer cache hit ratio

## Failover Procedures

### Automatic Failover (Patroni/CloudNativePG)

With operators like Patroni or CloudNativePG, failover is automatic:

1. Primary failure detected
2. Leader election among replicas
3. New primary promoted
4. Other replicas reconfigured
5. DNS/service updated

### Manual Failover

```bash
# On replica to promote
sudo -u postgres pg_ctl promote -D /var/lib/postgresql/16/main

# Update application config
DB_HOST=new-primary.db.local
DB_READ_HOST_1=old-primary.db.local  # Now replica
```

### Planned Switchover

```sql
-- On current primary, verify replication is caught up
SELECT * FROM pg_stat_replication;

-- On replica to promote
SELECT pg_promote();

-- Reconfigure old primary as replica
```

## Performance Tuning

### Connection Pooling with PgBouncer

```ini
# pgbouncer.ini
[databases]
acs_primary = host=primary.db.local port=5432 dbname=acs
acs_replica = host=replica-1.db.local,replica-2.db.local port=5432 dbname=acs

[pgbouncer]
listen_addr = *
listen_port = 6432
pool_mode = transaction
max_client_conn = 10000
default_pool_size = 100
```

### Laravel Connection Pooling

```env
# Use PgBouncer for connection pooling
DB_HOST=pgbouncer.local
DB_PORT=6432
```

### Query Optimization

```php
// Force read from primary for critical queries
$device = DB::connection('pgsql')->table('cpe_devices')
    ->useWritePdo()  // Force primary
    ->where('serial_number', $serial)
    ->first();

// Explicit replica read for reports
$stats = DB::connection('pgsql')->table('device_stats')
    ->select(DB::raw('COUNT(*) as total'))
    ->first();  // Goes to replica automatically
```

## Capacity Planning

| Devices | Primary | Replicas | Connections |
|---------|---------|----------|-------------|
| 10,000 | 4 vCPU, 16GB | 1x 2 vCPU, 8GB | 200 |
| 50,000 | 8 vCPU, 32GB | 2x 4 vCPU, 16GB | 500 |
| 100,000 | 16 vCPU, 64GB | 3x 8 vCPU, 32GB | 1,000 |
| 500,000 | 32 vCPU, 128GB | 5x 16 vCPU, 64GB | 5,000 |

### Storage Estimates

| Devices | Data Size | WAL/Day | Backup Size |
|---------|-----------|---------|-------------|
| 10,000 | 5GB | 1GB | 6GB |
| 50,000 | 25GB | 5GB | 30GB |
| 100,000 | 50GB | 10GB | 60GB |
| 500,000 | 250GB | 50GB | 300GB |

## Troubleshooting

### Replication Lag

```sql
-- Check lag on primary
SELECT 
    client_addr,
    state,
    pg_wal_lsn_diff(sent_lsn, replay_lsn) AS lag_bytes,
    replay_lag
FROM pg_stat_replication;
```

**Common causes:**
- Network latency between primary and replica
- Replica under heavy read load
- Slow storage on replica
- Long-running queries on replica

### Connection Issues

```bash
# Test connectivity
psql -h replica-1.db.local -U acs_user -d acs -c "SELECT 1"

# Check connection limits
psql -c "SELECT count(*) FROM pg_stat_activity"
```

### Split-Brain Prevention

Always use a distributed consensus mechanism (Patroni, etcd) for automatic failover to prevent split-brain scenarios where multiple nodes think they are primary.

## Security Best Practices

1. **SSL/TLS**: Always use `DB_SSLMODE=require` in production
2. **Network Isolation**: Place database in private subnet
3. **Firewall**: Only allow connections from application servers
4. **Credentials**: Use separate credentials for replication
5. **Encryption at Rest**: Enable storage encryption
6. **Audit Logging**: Enable `pgaudit` for compliance

```sql
-- Enable pgaudit
CREATE EXTENSION pgaudit;
ALTER SYSTEM SET pgaudit.log = 'write, ddl';
SELECT pg_reload_conf();
```

## Related Documentation

- [Redis Cluster Guide](./REDIS_CLUSTER_GUIDE.md)
- [Multi-Tenant Architecture](./MULTI_TENANT_AUTH_ROADMAP.md)
- [Kubernetes Deployment](./KUBERNETES_DEPLOYMENT.md)
- [Security Hardening](./SECURITY_HARDENING.md)
