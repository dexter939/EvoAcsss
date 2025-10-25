# ACS Load Testing - Bottleneck Identification Guide

## Overview

Questa guida ti aiuta a identificare, diagnosticare, e risolvere performance bottleneck nel sistema ACS usando i risultati dei load test K6 e Prometheus/Grafana metrics.

---

## Common Bottlenecks

### 1. Database Performance

#### Symptoms

**Dall'output K6**:
```
✗ http_req_duration p95 < 500ms
  ↳ 95% — ✗ 1,234ms (FAILED)
```

**Da Grafana**:
- Response time p95 > 800ms
- Database connection pool > 80%
- High query execution time

**Da Logs**:
```
[WARNING] Slow query detected (1234ms): SELECT * FROM devices WHERE ...
[WARNING] Database connection pool exhausted
```

#### Root Causes

1. **Missing Database Indexes**
   ```sql
   -- Query senza index
   SELECT * FROM devices WHERE serial_number = 'ABC123';
   -- Missing index on serial_number column
   ```

2. **N+1 Query Problem**
   ```php
   // BAD: N+1 queries
   $devices = Device::all();
   foreach ($devices as $device) {
       echo $device->manufacturer->name;  // +1 query per device
   }
   
   // GOOD: Eager loading
   $devices = Device::with('manufacturer')->get();
   ```

3. **Full Table Scans**
   ```sql
   -- Query che scansiona tutta la tabella
   SELECT * FROM devices WHERE LOWER(model) = 'router';
   -- Needs functional index: CREATE INDEX idx_devices_model_lower ON devices (LOWER(model));
   ```

4. **Large Result Sets**
   ```php
   // BAD: Carica 100K devices in memoria
   $devices = Device::all();
   
   // GOOD: Usa pagination o chunking
   Device::chunk(1000, function($devices) {
       // Process 1000 devices at a time
   });
   ```

#### Diagnosis Steps

**Step 1: Identify Slow Queries**
```sql
-- PostgreSQL slow query log
SELECT 
    query,
    calls,
    mean_exec_time,
    max_exec_time
FROM pg_stat_statements
WHERE mean_exec_time > 100  -- ms
ORDER BY mean_exec_time DESC
LIMIT 20;
```

**Step 2: Explain Query Plan**
```sql
EXPLAIN ANALYZE
SELECT * FROM devices 
WHERE manufacturer_id = 123 
AND status = 'online'
ORDER BY created_at DESC
LIMIT 50;

-- Look for:
-- ✅ "Index Scan" (good)
-- ❌ "Seq Scan" (bad - full table scan)
-- ❌ "execution time: 1234ms" (bad - slow)
```

**Step 3: Check Index Usage**
```sql
-- Find tables without indexes
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
AND tablename NOT IN (
    SELECT tablename FROM pg_indexes WHERE schemaname = 'public'
)
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
```

**Step 4: Monitor Connection Pool**
```sql
-- Check active connections
SELECT count(*) FROM pg_stat_activity;

-- Check connection states
SELECT state, count(*) 
FROM pg_stat_activity 
GROUP BY state;
```

#### Solutions

**1. Add Missing Indexes**
```sql
-- Single column index
CREATE INDEX idx_devices_serial_number ON devices(serial_number);

-- Composite index
CREATE INDEX idx_devices_status_created ON devices(status, created_at DESC);

-- Partial index (for common filters)
CREATE INDEX idx_devices_online ON devices(id) WHERE status = 'online';

-- Functional index
CREATE INDEX idx_devices_model_lower ON devices(LOWER(model));
```

**2. Optimize Queries**
```php
// Use select() to load only needed columns
Device::select('id', 'serial_number', 'status')
    ->where('status', 'online')
    ->get();

// Use eager loading for relationships
Device::with(['manufacturer', 'customer'])
    ->where('status', 'online')
    ->get();

// Use cursor for large datasets
foreach (Device::cursor() as $device) {
    // Process one device at a time
}
```

**3. Increase Connection Pool**
```env
# .env
DB_CONNECTION_POOL_MIN=10
DB_CONNECTION_POOL_MAX=100
```

```yaml
# config/database.php (PostgreSQL)
'pgsql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
    'pool' => [
        'min' => 10,
        'max' => 100,
    ],
],
```

**4. Implement Query Caching**
```php
// Cache expensive queries
$devices = Cache::remember('devices.online', 300, function () {
    return Device::where('status', 'online')->count();
});
```

---

### 2. Cache Performance

#### Symptoms

**Da Grafana**:
- Cache hit ratio < 80%
- High Redis memory usage
- Frequent cache evictions

**Da Logs**:
```
[INFO] Cache miss: devices.123.parameters
[WARNING] Redis memory usage: 95% (7.6GB / 8GB)
[WARNING] Cache eviction in progress
```

#### Root Causes

1. **Low Hit Ratio**
   - Cache TTL too short
   - Cache warming not implemented
   - Cache keys too specific

2. **Memory Exhaustion**
   - Large cached values
   - No eviction policy
   - Memory leak

3. **Cache Stampede**
   - Popular keys expire simultaneously
   - No cache warming
   - No lock mechanism

#### Diagnosis Steps

**Step 1: Check Hit Ratio**
```bash
# Redis CLI
redis-cli INFO stats | grep keyspace

# Expected output:
# keyspace_hits:1000000
# keyspace_misses:50000
# Hit ratio: 95%
```

**Step 2: Check Memory Usage**
```bash
redis-cli INFO memory

# Look for:
# used_memory_human: 7.6G
# maxmemory: 8G
# evicted_keys: 1234
```

**Step 3: Analyze Cache Keys**
```bash
# Find largest keys
redis-cli --bigkeys

# Find key patterns
redis-cli KEYS "*devices*" | wc -l
```

**Step 4: Monitor Prometheus Metrics**
```promql
# Cache hit ratio
acs_cache_hits / (acs_cache_hits + acs_cache_misses) * 100

# Cache memory usage
acs_cache_memory_bytes / acs_cache_max_memory_bytes * 100
```

#### Solutions

**1. Optimize Cache Keys & TTL**
```php
// BAD: Too specific, low hit ratio
Cache::remember("devices.{$id}.params.{$timestamp}", 60, ...);

// GOOD: Generic, high hit ratio
Cache::remember("devices.{$id}.params", 3600, ...);

// Use tags for grouped invalidation
Cache::tags(['devices', "device:{$id}"])->remember(...);
```

**2. Implement Cache Warming**
```php
// Warm cache before expiration
Artisan::command('cache:warm', function () {
    $popularDevices = Device::where('access_count', '>', 1000)->get();
    
    foreach ($popularDevices as $device) {
        Cache::remember("devices.{$device->id}", 3600, function () use ($device) {
            return $device->load('parameters', 'manufacturer');
        });
    }
});
```

**3. Increase Redis Memory**
```yaml
# docker-compose.yml
services:
  redis:
    command: redis-server --maxmemory 16gb --maxmemory-policy allkeys-lru
```

**4. Implement Cache Locking (prevent stampede)**
```php
use Illuminate\Support\Facades\Cache;

$value = Cache::lock('expensive-query')->get(function () {
    return Cache::remember('key', 3600, function () {
        return DB::table('devices')->count();  // Expensive query
    });
});
```

---

### 3. Queue Performance

#### Symptoms

**Da Grafana**:
- Queue depth > 10,000 jobs
- Queue processing time > 60s
- Failed jobs increasing

**Da Logs**:
```
[WARNING] Queue backlog: 15,234 pending jobs
[ERROR] Job failed after 3 attempts: ProvisionDeviceJob
[WARNING] Worker timeout: 120s exceeded
```

#### Root Causes

1. **Insufficient Workers**
   - Too few worker processes
   - Workers not scaling

2. **Slow Job Processing**
   - Jobs take too long
   - Blocking operations
   - No timeout

3. **Failed Jobs**
   - External API timeouts
   - Database deadlocks
   - Memory exhaustion

#### Diagnosis Steps

**Step 1: Check Queue Metrics**
```bash
# Laravel Horizon dashboard
php artisan horizon:status

# Redis queue depth
redis-cli LLEN "queues:default"
```

**Step 2: Check Worker Performance**
```bash
# Laravel Horizon metrics
php artisan horizon:stats

# Expected output:
# Jobs Processed: 1,234,567
# Job Throughput: 123 jobs/sec
# Average Runtime: 2.34s
```

**Step 3: Identify Slow Jobs**
```php
// Check Horizon UI for:
// - Jobs > 30s runtime
// - Jobs with high failure rate
// - Jobs timing out
```

**Step 4: Monitor Prometheus Metrics**
```promql
# Queue processing rate
rate(acs_queue_jobs_processed_total[1m])

# Queue depth
acs_queue_depth

# Failed jobs rate
rate(acs_queue_jobs_failed_total[1m])
```

#### Solutions

**1. Scale Workers**
```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high-priority'],
            'balance' => 'auto',
            'minProcesses' => 10,    // Increase from 1
            'maxProcesses' => 50,    // Increase from 10
            'tries' => 3,
            'timeout' => 120,
        ],
    ],
],
```

**2. Optimize Job Processing**
```php
// BAD: Synchronous processing
class ProvisionDeviceJob implements ShouldQueue
{
    public function handle()
    {
        foreach ($this->devices as $device) {
            $this->provisionDevice($device);  // Slow
        }
    }
}

// GOOD: Batch processing + chunking
class ProvisionDeviceJob implements ShouldQueue
{
    public function handle()
    {
        collect($this->devices)
            ->chunk(100)
            ->each(function ($chunk) {
                // Process 100 devices at a time
                $this->provisionDevices($chunk);
            });
    }
}
```

**3. Add Job Timeout & Retry**
```php
class ProvisionDeviceJob implements ShouldQueue
{
    public $timeout = 120;  // 2 minutes
    public $tries = 3;      // Retry 3 times
    public $backoff = [10, 30, 60];  // Exponential backoff
    
    public function handle()
    {
        // Job logic with timeout
    }
    
    public function failed(Throwable $exception)
    {
        // Handle failed job
        Log::error("Job failed: {$exception->getMessage()}");
    }
}
```

**4. Use Priority Queues**
```php
// High priority jobs
ProvisionDeviceJob::dispatch($device)->onQueue('high-priority');

// Low priority jobs
GenerateReportJob::dispatch()->onQueue('low-priority');

// Configure workers
'supervisor-high' => [
    'queue' => ['high-priority'],
    'processes' => 20,
],
'supervisor-low' => [
    'queue' => ['low-priority'],
    'processes' => 5,
],
```

---

### 4. Memory Issues

#### Symptoms

**Da System Monitoring**:
- Memory usage > 80%
- OOM (Out of Memory) kills
- Swap usage increasing

**Da Logs**:
```
[ERROR] PHP Fatal error: Allowed memory size exhausted
[WARNING] Memory usage: 7.6GB / 8GB (95%)
[ERROR] Workflow killed: Out of memory
```

#### Root Causes

1. **Memory Leaks**
   - Objects not released
   - Circular references
   - Event listeners accumulating

2. **Large Data Sets**
   - Loading all devices in memory
   - Large Eloquent collections
   - No pagination

3. **Cache Bloat**
   - Too many cached items
   - Large cached values
   - No eviction

#### Diagnosis Steps

**Step 1: Monitor Memory Usage**
```bash
# Check current memory
free -h

# Monitor over time
watch -n 1 'free -h'

# Check process memory
ps aux --sort=-%mem | head -20
```

**Step 2: Profile PHP Memory**
```php
// Add to code
echo "Memory: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";
echo "Peak: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
```

**Step 3: Check for Leaks**
```bash
# Monitor PHP-FPM memory over time
watch -n 1 'ps aux | grep php-fpm | awk "{sum+=\$6} END {print sum/1024\" MB\"}"'
```

#### Solutions

**1. Increase PHP Memory Limit**
```ini
; php.ini
memory_limit = 512M  ; Increase from 256M

; For specific scripts
php -d memory_limit=1G artisan queue:work
```

**2. Use Chunking & Cursors**
```php
// BAD: Loads all 100K devices
$devices = Device::all();

// GOOD: Process in chunks
Device::chunk(1000, function ($devices) {
    foreach ($devices as $device) {
        // Process device
    }
    
    // Memory freed after each chunk
});

// BEST: Use cursor (one at a time)
foreach (Device::cursor() as $device) {
    // Process device
    // Minimal memory usage
}
```

**3. Unset Large Variables**
```php
$largeArray = Device::with('parameters')->get();

// Process data
foreach ($largeArray as $device) {
    // ...
}

// Free memory
unset($largeArray);
gc_collect_cycles();  // Force garbage collection
```

**4. Optimize Cache Storage**
```php
// BAD: Cache entire device with all relations
Cache::remember("device.{$id}", 3600, function () use ($id) {
    return Device::with('parameters', 'alarms', 'logs')->find($id);
});

// GOOD: Cache only needed data
Cache::remember("device.{$id}.summary", 3600, function () use ($id) {
    return Device::select('id', 'serial_number', 'status')->find($id);
});
```

---

### 5. Network I/O

#### Symptoms

**Da K6 Output**:
- High connection timeout rate
- Request queue building up
- Connection refused errors

**Da Logs**:
```
[ERROR] Connection timeout after 30s
[WARNING] Too many open connections
[ERROR] ECONNREFUSED: Connection refused
```

#### Root Causes

1. **Too Few Connections**
   - Default connection limits too low
   - No connection pooling

2. **Slow Network**
   - High latency
   - Packet loss
   - Bandwidth saturation

3. **File Descriptor Limits**
   - ulimit too low
   - Too many open sockets

#### Solutions

**1. Increase Connection Limits**
```bash
# System-wide limits
sudo sysctl -w net.core.somaxconn=4096
sudo sysctl -w net.ipv4.tcp_max_syn_backlog=4096

# File descriptor limits
ulimit -n 65536

# Make permanent in /etc/sysctl.conf
net.core.somaxconn=4096
net.ipv4.tcp_max_syn_backlog=4096
```

**2. Optimize PHP-FPM**
```ini
; /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 100      ; Increase from 50
pm.start_servers = 20      ; Increase from 5
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.max_requests = 500
```

**3. Use HTTP/2 & Keep-Alive**
```nginx
# nginx.conf
http {
    keepalive_timeout 65;
    keepalive_requests 100;
    
    upstream php-fpm {
        server unix:/var/run/php-fpm.sock;
        keepalive 32;
    }
}
```

---

## Bottleneck Identification Workflow

### Step-by-Step Process

```
1. Run Load Test
   ↓
2. Check K6 Output
   - Which thresholds failed?
   - What's the p95/p99?
   - Error rate?
   ↓
3. Check Grafana Dashboards
   - Response time trends
   - System resource usage
   - Protocol-specific metrics
   ↓
4. Check Application Logs
   - Slow query warnings
   - Memory warnings
   - Queue backlog
   ↓
5. Identify Bottleneck Category
   - Database?
   - Cache?
   - Queue?
   - Memory?
   - Network?
   ↓
6. Apply Targeted Solution
   (See sections above)
   ↓
7. Re-run Load Test
   ↓
8. Validate Improvement
```

---

## Quick Reference: Bottleneck → Solution

| Symptom | Likely Bottleneck | Quick Fix |
|---------|------------------|-----------|
| p95 > 1s | Database slow queries | Add indexes, optimize queries |
| Cache hit < 80% | Cache configuration | Increase TTL, implement warming |
| Queue depth > 10K | Insufficient workers | Scale workers, optimize jobs |
| Memory > 80% | Memory leak | Use chunking, unset variables |
| Connection errors | Network limits | Increase ulimit, optimize FPM |
| Error rate > 5% | Application bugs | Check logs, fix errors |
| CPU > 90% | Compute bottleneck | Scale horizontally, optimize code |

---

## Next Steps

After identifying bottlenecks:

1. **Document Findings**: Add to PERFORMANCE-RESULTS.md
2. **Apply Fixes**: See OPTIMIZATION.md
3. **Re-test**: Run load tests again
4. **Verify**: Check Grafana dashboards
5. **Deploy**: Push to production

**Need Help?**
- See: OPTIMIZATION.md for detailed solutions
- See: PERFORMANCE-RESULTS.md for tracking improvements
- See: tests/Load/README.md for test execution
