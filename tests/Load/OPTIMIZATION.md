# ACS Load Testing - Optimization Recommendations

## Overview

Questa guida fornisce optimization recommendations basate sui risultati dei load test per raggiungere carrier-grade performance (100K+ devices).

---

## Optimization Categories

### 1. Database Optimization

#### A. Index Strategy

**Analyze Query Patterns**:
```bash
# Run load test and capture slow queries
./tests/Load/run-tests.sh mixed > test-results.log

# Check PostgreSQL slow query log
tail -f /var/log/postgresql/postgresql-15-main.log | grep "duration:"
```

**Create Strategic Indexes**:

```sql
-- ===================================
-- ESSENTIAL INDEXES (must-have)
-- ===================================

-- Devices table
CREATE INDEX CONCURRENTLY idx_devices_serial_number ON devices(serial_number);
CREATE INDEX CONCURRENTLY idx_devices_status ON devices(status);
CREATE INDEX CONCURRENTLY idx_devices_customer_id ON devices(customer_id);
CREATE INDEX CONCURRENTLY idx_devices_manufacturer_id ON devices(manufacturer_id);
CREATE INDEX CONCURRENTLY idx_devices_last_inform ON devices(last_inform_at);

-- Composite indexes for common queries
CREATE INDEX CONCURRENTLY idx_devices_status_created 
    ON devices(status, created_at DESC);
    
CREATE INDEX CONCURRENTLY idx_devices_customer_status 
    ON devices(customer_id, status) 
    WHERE status IN ('online', 'offline');

-- Parameters table
CREATE INDEX CONCURRENTLY idx_parameters_device_id ON parameters(device_id);
CREATE INDEX CONCURRENTLY idx_parameters_path ON parameters(path);
CREATE INDEX CONCURRENTLY idx_parameters_device_path 
    ON parameters(device_id, path);

-- Sessions table
CREATE INDEX CONCURRENTLY idx_tr069_sessions_device_id 
    ON tr069_sessions(device_id);
CREATE INDEX CONCURRENTLY idx_tr069_sessions_created 
    ON tr069_sessions(created_at DESC);

-- Alarms table
CREATE INDEX CONCURRENTLY idx_alarms_device_id ON alarms(device_id);
CREATE INDEX CONCURRENTLY idx_alarms_severity ON alarms(severity);
CREATE INDEX CONCURRENTLY idx_alarms_status ON alarms(status);
CREATE INDEX CONCURRENTLY idx_alarms_device_status 
    ON alarms(device_id, status) 
    WHERE status = 'active';

-- ===================================
-- ADVANCED INDEXES (performance boost)
-- ===================================

-- Partial indexes (smaller, faster)
CREATE INDEX CONCURRENTLY idx_devices_online 
    ON devices(id, last_inform_at) 
    WHERE status = 'online';

CREATE INDEX CONCURRENTLY idx_alarms_active 
    ON alarms(device_id, created_at DESC) 
    WHERE status = 'active';

-- Functional indexes (for LIKE queries)
CREATE INDEX CONCURRENTLY idx_devices_serial_lower 
    ON devices(LOWER(serial_number));

CREATE INDEX CONCURRENTLY idx_devices_model_lower 
    ON devices(LOWER(model));

-- GIN index for JSONB columns
CREATE INDEX CONCURRENTLY idx_parameters_value_gin 
    ON parameters USING gin(value);

-- Covering indexes (index-only scans)
CREATE INDEX CONCURRENTLY idx_devices_list_covering 
    ON devices(customer_id, status, serial_number, model, ip_address);
```

**Monitor Index Usage**:
```sql
-- Find unused indexes
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_scan,
    pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
AND indexrelname NOT LIKE 'pg_toast%'
ORDER BY pg_relation_size(indexrelid) DESC;

-- Find missing indexes
SELECT 
    schemaname,
    tablename,
    seq_scan,
    seq_tup_read,
    idx_scan,
    seq_tup_read / seq_scan AS avg_seq_tup_read
FROM pg_stat_user_tables
WHERE seq_scan > 0
ORDER BY seq_tup_read DESC
LIMIT 20;
```

#### B. Query Optimization

**Use EXPLAIN ANALYZE**:
```sql
EXPLAIN (ANALYZE, BUFFERS, VERBOSE)
SELECT d.*, m.name AS manufacturer_name
FROM devices d
JOIN manufacturers m ON d.manufacturer_id = m.id
WHERE d.status = 'online'
AND d.customer_id = 123
ORDER BY d.last_inform_at DESC
LIMIT 50;
```

**Optimize Common Queries**:

```php
// ===================================
// DEVICE LISTING (most common query)
// ===================================

// BAD: N+1 queries
$devices = Device::where('customer_id', $customerId)->get();
foreach ($devices as $device) {
    echo $device->manufacturer->name;  // +1 query per device
    echo $device->alarms_count;        // +1 query per device
}

// GOOD: Eager loading + aggregates
$devices = Device::where('customer_id', $customerId)
    ->with('manufacturer:id,name')  // Only load needed columns
    ->withCount(['alarms' => function ($query) {
        $query->where('status', 'active');
    }])
    ->select([
        'id', 'serial_number', 'model', 'status', 
        'manufacturer_id', 'last_inform_at'
    ])
    ->orderBy('last_inform_at', 'desc')
    ->paginate(50);

// ===================================
// DEVICE SEARCH
// ===================================

// BAD: LIKE with wildcards (slow)
$devices = Device::where('serial_number', 'LIKE', '%ABC%')->get();

// GOOD: Use indexed column + prefix search
$devices = Device::where('serial_number', 'LIKE', 'ABC%')->get();

// BETTER: Use full-text search for complex queries
$devices = Device::whereRaw('serial_number ILIKE ?', ["%{$search}%"])
    ->limit(100)
    ->get();

// BEST: Use dedicated search index (Elasticsearch, MeiliSearch)
$devices = Device::search($query)->get();

// ===================================
// PARAMETER QUERIES
// ===================================

// BAD: Multiple queries for parameters
$device = Device::find($id);
$params = $device->parameters;  // Loads ALL parameters

foreach ($params as $param) {
    if ($param->path === 'InternetGatewayDevice.DeviceInfo.ModelName') {
        echo $param->value;
    }
}

// GOOD: Query specific parameters
$modelName = Parameter::where('device_id', $id)
    ->where('path', 'InternetGatewayDevice.DeviceInfo.ModelName')
    ->value('value');  // Returns single value, not collection

// BETTER: Use caching for frequently accessed parameters
$modelName = Cache::remember("device.{$id}.model", 3600, function () use ($id) {
    return Parameter::where('device_id', $id)
        ->where('path', 'InternetGatewayDevice.DeviceInfo.ModelName')
        ->value('value');
});
```

#### C. Connection Pool Tuning

**PostgreSQL Configuration** (`postgresql.conf`):
```ini
# Connection settings
max_connections = 100              # Increase if needed (but prefer pooling)
shared_buffers = 4GB               # 25% of system RAM
effective_cache_size = 12GB        # 75% of system RAM
work_mem = 16MB                    # Per operation memory
maintenance_work_mem = 1GB         # For VACUUM, CREATE INDEX

# Query optimization
random_page_cost = 1.1             # For SSD (default: 4.0)
effective_io_concurrency = 200     # For SSD (default: 1)
default_statistics_target = 100    # Better query plans

# Write performance
wal_buffers = 16MB
min_wal_size = 1GB
max_wal_size = 4GB
checkpoint_completion_target = 0.9

# Logging (for troubleshooting)
log_min_duration_statement = 100   # Log queries > 100ms
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on
```

**Laravel Database Configuration**:
```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
    'options' => [
        // Enable persistent connections
        PDO::ATTR_PERSISTENT => true,
        
        // Set timeout
        PDO::ATTR_TIMEOUT => 30,
        
        // Error mode
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
],
```

#### D. Database Maintenance

**Regular Maintenance Tasks**:
```bash
#!/bin/bash
# database-maintenance.sh

# 1. Vacuum (reclaim space)
psql -U acs_user acs_db -c "VACUUM ANALYZE;"

# 2. Reindex (rebuild indexes)
psql -U acs_user acs_db -c "REINDEX DATABASE acs_db;"

# 3. Update statistics
psql -U acs_user acs_db -c "ANALYZE;"

# 4. Check bloat
psql -U acs_user acs_db -f check_bloat.sql

# Schedule via cron:
# 0 2 * * 0 /path/to/database-maintenance.sh  # Weekly at 2 AM Sunday
```

---

### 2. Cache Optimization

#### A. Multi-Tier Caching Strategy

**Layer 1: Application Cache (Redis)**
```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Redis connection
'redis' => [
    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,  // Separate DB for cache
    ],
],
```

**Layer 2: Query Result Cache**
```php
// CacheService.php
class CacheService
{
    // Short-lived cache (5 minutes)
    public function getDeviceStatus($deviceId)
    {
        return Cache::remember("device.{$deviceId}.status", 300, function () use ($deviceId) {
            return Device::find($deviceId)->status;
        });
    }
    
    // Medium-lived cache (1 hour)
    public function getDeviceParameters($deviceId)
    {
        return Cache::remember("device.{$deviceId}.params", 3600, function () use ($deviceId) {
            return Parameter::where('device_id', $deviceId)->get();
        });
    }
    
    // Long-lived cache (24 hours)
    public function getManufacturers()
    {
        return Cache::remember('manufacturers.all', 86400, function () {
            return Manufacturer::all();
        });
    }
}
```

**Layer 3: API Response Cache**
```php
// Middleware: CacheResponse.php
class CacheResponse
{
    public function handle($request, Closure $next)
    {
        $key = 'response.' . md5($request->fullUrl());
        
        if ($cached = Cache::get($key)) {
            return response()->json($cached);
        }
        
        $response = $next($request);
        
        // Cache successful responses
        if ($response->status() === 200) {
            Cache::put($key, $response->getData(), 300);  // 5 minutes
        }
        
        return $response;
    }
}
```

#### B. Cache Warming

**Warm Popular Data on Deploy**:
```php
// Artisan command: cache:warm
Artisan::command('cache:warm', function () {
    $this->info('Warming cache...');
    
    // Warm manufacturers (rarely changes)
    Cache::remember('manufacturers.all', 86400, function () {
        return Manufacturer::all();
    });
    
    // Warm top 1000 devices
    $topDevices = Device::orderBy('access_count', 'desc')
        ->limit(1000)
        ->get();
    
    foreach ($topDevices as $device) {
        Cache::remember("device.{$device->id}.params", 3600, function () use ($device) {
            return Parameter::where('device_id', $device->id)->get();
        });
    }
    
    $this->info('Cache warmed successfully!');
})->purpose('Warm application cache with frequently accessed data');

// Run on deploy:
// php artisan cache:warm
```

**Warm on Demand**:
```php
// Event listener: DeviceUpdated
class WarmDeviceCache
{
    public function handle(DeviceUpdated $event)
    {
        $device = $event->device;
        
        // Invalidate old cache
        Cache::forget("device.{$device->id}.params");
        
        // Warm new cache
        Cache::remember("device.{$device->id}.params", 3600, function () use ($device) {
            return Parameter::where('device_id', $device->id)->get();
        });
    }
}
```

#### C. Redis Configuration

**redis.conf Optimization**:
```ini
# Memory
maxmemory 8gb
maxmemory-policy allkeys-lru  # Evict least recently used keys

# Persistence (disable for pure cache)
save ""                        # Disable RDB snapshots
appendonly no                  # Disable AOF

# Performance
tcp-backlog 511
timeout 300
tcp-keepalive 60
```

**Monitor Redis Performance**:
```bash
# Check memory usage
redis-cli INFO memory

# Check hit ratio
redis-cli INFO stats | grep keyspace

# Monitor slow commands
redis-cli SLOWLOG GET 10

# Monitor in real-time
redis-cli --latency
redis-cli --stat
```

---

### 3. Queue Optimization

#### A. Worker Scaling

**Horizon Configuration** (`config/horizon.php`):
```php
'environments' => [
    'production' => [
        // High-priority queue (TR-069/TR-369)
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high-priority', 'tr069', 'tr369'],
            'balance' => 'auto',
            'minProcesses' => 10,
            'maxProcesses' => 50,
            'balanceMaxShift' => 5,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 120,
            'memory' => 512,
        ],
        
        // Default queue (provisioning, firmware)
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'provisioning', 'firmware'],
            'balance' => 'auto',
            'minProcesses' => 5,
            'maxProcesses' => 30,
            'tries' => 3,
            'timeout' => 300,
        ],
        
        // Low-priority queue (reports, cleanup)
        'supervisor-low' => [
            'connection' => 'redis',
            'queue' => ['low-priority', 'reports'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 1,
            'timeout' => 600,
        ],
    ],
],
```

#### B. Job Optimization

**Batch Processing**:
```php
// BAD: One job per device
foreach ($devices as $device) {
    ProvisionDeviceJob::dispatch($device);  // 100K jobs!
}

// GOOD: Batch jobs
$devices->chunk(100)->each(function ($chunk) {
    ProvisionDevicesBatchJob::dispatch($chunk);  // 1K jobs
});

// BETTER: Use Laravel's batch feature
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

Bus::batch(
    $devices->chunk(100)->map(function ($chunk) {
        return new ProvisionDevicesBatchJob($chunk);
    })
)->then(function (Batch $batch) {
    // All jobs completed
})->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure
})->finally(function (Batch $batch) {
    // Batch finished
})->dispatch();
```

**Async Processing**:
```php
// Job with chunking and progress tracking
class ProvisionDevicesBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 300;
    public $tries = 3;
    public $backoff = [60, 180, 600];
    
    public function __construct(public Collection $devices) {}
    
    public function handle()
    {
        if ($this->batch()->cancelled()) {
            return;
        }
        
        $this->devices->each(function ($device, $index) {
            try {
                $this->provisionDevice($device);
                
                // Update progress every 10 devices
                if ($index % 10 === 0) {
                    $this->batch()->progress(
                        ($index + 1) / $this->devices->count() * 100
                    );
                }
            } catch (\Exception $e) {
                Log::error("Failed to provision device {$device->id}: {$e->getMessage()}");
                // Continue with next device instead of failing entire batch
            }
        });
    }
    
    public function failed(Throwable $exception)
    {
        // Handle failed batch
        Log::error("Batch provisioning failed: {$exception->getMessage()}");
    }
}
```

---

### 4. Application-Level Optimization

#### A. Response Optimization

**API Response Caching**:
```php
// routes/api.php
Route::middleware(['cache.response:300'])->group(function () {
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/manufacturers', [ManufacturerController::class, 'index']);
});

// Middleware: CacheResponse
class CacheResponse
{
    public function handle($request, Closure $next, $ttl = 300)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }
        
        $key = $this->getCacheKey($request);
        
        return Cache::remember($key, $ttl, function () use ($request, $next) {
            return $next($request);
        });
    }
    
    protected function getCacheKey($request)
    {
        return 'response.' . md5(
            $request->fullUrl() . 
            $request->header('Authorization')
        );
    }
}
```

**JSON Response Optimization**:
```php
// API Resource: DeviceResource.php
class DeviceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'status' => $this->status,
            
            // Only include if requested
            'manufacturer' => $this->when(
                $request->include === 'manufacturer',
                new ManufacturerResource($this->manufacturer)
            ),
            
            // Lazy load parameters
            'parameters' => $this->when(
                $request->include === 'parameters',
                ParameterResource::collection($this->parameters)
            ),
        ];
    }
}

// Usage:
// /api/v1/devices              - Basic info only
// /api/v1/devices?include=manufacturer  - Include manufacturer
// /api/v1/devices?include=parameters    - Include parameters
```

#### B. Code-Level Optimization

**Avoid N+1 Queries**:
```php
// Use Laravel Debugbar or Telescope to identify N+1 queries
// composer require barryvdh/laravel-debugbar --dev

// Common N+1 patterns and fixes:

// Pattern 1: Relationship loading
// BAD
$devices = Device::all();
foreach ($devices as $device) {
    echo $device->manufacturer->name;  // N+1
}

// GOOD
$devices = Device::with('manufacturer')->all();

// Pattern 2: Counts
// BAD
$devices = Device::all();
foreach ($devices as $device) {
    echo $device->alarms->count();  // N+1
}

// GOOD
$devices = Device::withCount('alarms')->all();

// Pattern 3: Nested relationships
// BAD
$customers = Customer::all();
foreach ($customers as $customer) {
    foreach ($customer->devices as $device) {  // N+1
        echo $device->manufacturer->name;      // N+1
    }
}

// GOOD
$customers = Customer::with('devices.manufacturer')->all();
```

**Use Select to Load Only Needed Columns**:
```php
// BAD: Loads all columns (including large JSONB)
$devices = Device::all();

// GOOD: Only load needed columns
$devices = Device::select(['id', 'serial_number', 'model', 'status'])->get();

// BETTER: With relationships
$devices = Device::with(['manufacturer' => function ($query) {
    $query->select('id', 'name');
}])->select(['id', 'serial_number', 'manufacturer_id'])->get();
```

---

### 5. Infrastructure Optimization

#### A. Horizontal Scaling

**Load Balancer Configuration (Nginx)**:
```nginx
upstream acs_backend {
    least_conn;  # Load balancing algorithm
    
    server acs-app-1:80 weight=3 max_fails=3 fail_timeout=30s;
    server acs-app-2:80 weight=3 max_fails=3 fail_timeout=30s;
    server acs-app-3:80 weight=2 max_fails=3 fail_timeout=30s;
    
    keepalive 32;
}

server {
    listen 80;
    server_name acs.example.com;
    
    location / {
        proxy_pass http://acs_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

#### B. Auto-Scaling (Kubernetes)

**HPA Configuration**:
```yaml
# deployment/k8s/hpa.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: acs-app-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: acs-app
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
    - type: Pods
      pods:
        metric:
          name: http_requests_per_second
        target:
          type: AverageValue
          averageValue: "1000"
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
        - type: Percent
          value: 50
          periodSeconds: 60
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
        - type: Percent
          value: 100
          periodSeconds: 30
        - type: Pods
          value: 4
          periodSeconds: 30
      selectPolicy: Max
```

---

## Optimization Workflow

```
1. Run Baseline Test
   ↓
2. Identify Bottlenecks
   (See BOTTLENECKS.md)
   ↓
3. Apply Optimizations
   (This guide)
   ↓
4. Re-run Test
   ↓
5. Measure Improvement
   ↓
6. Document Results
   (PERFORMANCE-RESULTS.md)
   ↓
7. Repeat Until Targets Met
```

---

## Performance Targets Checklist

### Infrastructure Hardening (Current Phase)

- [ ] Database query p95 < 50ms
- [ ] Database connection pool < 80%
- [ ] Cache hit ratio > 80%
- [ ] Queue processing > 1000 jobs/sec
- [ ] Memory usage < 80%
- [ ] CPU usage < 80%

### Functional Validation (Before Production)

- [ ] API p95 < 500ms, p99 < 1000ms
- [ ] TR-069 p95 < 300ms, p99 < 600ms
- [ ] TR-369 p95 < 400ms, p99 < 800ms
- [ ] Error rate < 1%
- [ ] Success rate > 99%
- [ ] 24h soak test stable

### Production Deployment

- [ ] 100K concurrent devices supported
- [ ] 99.9% uptime achieved
- [ ] Auto-scaling configured
- [ ] Monitoring & alerting active
- [ ] Backup & recovery tested

---

## Next Steps

1. **Apply Optimizations**: Implement recommendations from this guide
2. **Run Tests**: Execute load tests to validate improvements
3. **Monitor**: Use Grafana dashboards to track metrics
4. **Document**: Record results in PERFORMANCE-RESULTS.md
5. **Deploy**: Push optimizations to production

**See Also**:
- BOTTLENECKS.md - Identify performance issues
- PERFORMANCE-RESULTS.md - Track improvements
- tests/Load/README.md - Run load tests
