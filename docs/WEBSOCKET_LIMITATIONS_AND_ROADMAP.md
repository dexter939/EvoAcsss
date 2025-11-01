# WebSocket Broadcasting - Current Limitations & Roadmap

## CRITICAL LIMITATION: Incomplete Tenant Coverage

### Problem Statement
The current WebSocket alarm broadcasting implementation has a **critical architectural limitation** due to the absence of `tenant_id`/`customer_id` in the data model:

**Current Behavior**:
- Alarms broadcast ONLY to users with **explicit device shares** in `user_devices` pivot table
- Users who inherit device access through **tenant membership** (without explicit pivot entry) **DO NOT receive broadcasts**
- This affects the majority of tenant operators in typical deployments

**Root Cause**:
- Database schema lacks `tenant_id`/`customer_id` columns in `cpe_devices` and `alarms` tables
- Multi-tenant access control relies solely on `user_devices` pivot for explicit shares
- No way to identify "all users in a device's tenant" without tenant ownership field

### Impact Assessment

#### What Works ✅
- Users with explicit `user_devices` pivot entries receive alarms correctly
- Multi-tenant isolation is secure (zero cross-tenant leakage)
- Backward compatibility maintained for legacy web clients
- Admin users with explicit shares receive notifications

#### What Doesn't Work ❌
- **Tenant operators without explicit device shares miss alarms**
- **Device-less alarms are not broadcast** (e.g., account-level alerts)
- **Majority of users in typical tenant setups receive no notifications**

### Recommended Solutions

#### Option 1: Add Tenant Ownership (RECOMMENDED)
**Best long-term solution** - Adds proper tenant hierarchy to data model.

**Implementation**:
1. Add database migration:
   ```sql
   ALTER TABLE cpe_devices ADD COLUMN tenant_id INTEGER REFERENCES tenants(id);
   ALTER TABLE alarms ADD COLUMN tenant_id INTEGER;
   CREATE INDEX idx_devices_tenant ON cpe_devices(tenant_id);
   CREATE INDEX idx_alarms_tenant ON alarms(tenant_id);
   ```

2. Add `tenants` table if not exists:
   ```sql
   CREATE TABLE tenants (
     id SERIAL PRIMARY KEY,
     name VARCHAR(255) NOT NULL,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   
   ALTER TABLE users ADD COLUMN tenant_id INTEGER REFERENCES tenants(id);
   ```

3. Update `AlarmCreated` event:
   ```php
   public function broadcastOn(): array
   {
       $channels = [];
       
       // Get tenant_id from device or alarm
       $tenantId = $this->alarm->tenant_id ?? $this->alarm->device?->tenant_id;
       
       if (!$tenantId) {
           Log::warning('Alarm without tenant, not broadcast', ['alarm_id' => $this->alarm->id]);
           return [];
       }
       
       // Get ALL users in tenant
       $tenantUsers = User::where('tenant_id', $tenantId)->get();
       
       foreach ($tenantUsers as $user) {
           $channels[] = new PrivateChannel('user.' . $user->id);
       }
       
       return $channels;
   }
   ```

4. Update channel authorization:
   ```php
   Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
       return $user->tenant_id === (int) $tenantId;
   });
   ```

**Benefits**:
- ✅ Complete tenant coverage - all users receive alarms
- ✅ Proper tenant hierarchy in data model
- ✅ Enables tenant-scoped channels
- ✅ Supports device-less alarms
- ✅ Scalable to 100K+ devices

**Effort**: Medium (1-2 days for migration + testing)

#### Option 2: Enhanced User Discovery (WORKAROUND)
**Temporary solution** - Works without schema changes but less efficient.

**Implementation**:
Add method to `CpeDevice` model:
```php
/**
 * Get all users who can access this device (explicit + inherited)
 * Uses GlobalScope to determine visibility
 */
public function getAllAuthorizedUsers(): Collection
{
    // Get explicit shares
    $explicitUsers = $this->users()->get();
    
    // Get users who can see this device via GlobalScope
    // (tenant membership, department, role, etc.)
    $allPotentialUsers = User::all();
    $authorizedUsers = collect();
    
    foreach ($allPotentialUsers as $user) {
        if ($user->canAccessDevice($this)) {
            $authorizedUsers->push($user);
        }
    }
    
    return $authorizedUsers->unique('id');
}
```

Update `AlarmCreated::broadcastOn()`:
```php
$authorizedUsers = $this->alarm->device?->getAllAuthorizedUsers() ?? collect();
```

**Benefits**:
- ✅ No schema changes required
- ✅ Works with existing access control logic
- ✅ Complete user coverage

**Drawbacks**:
- ❌ Performance: O(N) user iteration on every alarm
- ❌ Not scalable to 100K+ devices
- ❌ Doesn't solve device-less alarms

**Effort**: Low (few hours)

#### Option 3: Queue-Based Broadcasting (HYBRID)
**Scales well** - Offload broadcast logic to queue workers.

**Implementation**:
```php
// In AlarmCreated event
public function broadcastOn(): array
{
    // Dispatch job to determine recipients asynchronously
    BroadcastAlarmToTenant::dispatch($this->alarm);
    return []; // Event itself doesn't broadcast
}

// New job: BroadcastAlarmToTenant
class BroadcastAlarmToTenant implements ShouldQueue
{
    public function handle()
    {
        $device = $this->alarm->device;
        if (!$device) return;
        
        // Expensive operation runs in queue
        $users = $device->getAllAuthorizedUsers();
        
        foreach ($users as $user) {
            // Direct broadcast via Reverb
            broadcast(new AlarmNotification($this->alarm))->toOthers()
                ->via(new PrivateChannel('user.' . $user->id));
        }
    }
}
```

**Benefits**:
- ✅ Non-blocking alarm creation
- ✅ Scalable via queue workers
- ✅ Can implement complex user discovery logic

**Effort**: Medium (1 day)

### Current Production Status

⚠️ **NOT PRODUCTION-READY** for typical tenant deployments where users inherit access through tenant membership.

**Safe to deploy IF**:
- All users have explicit `user_devices` pivot entries (uncommon)
- OR Only using for admin/super-admin notifications
- OR Acceptable that most tenant operators miss alarm broadcasts

**NOT safe to deploy IF**:
- Tenant operators rely on inherited access (typical case)
- Device-less alarms are critical
- Complete alarm coverage is required

### Immediate Action Items

1. **DECIDE on solution**: Option 1 (tenant_id), Option 2 (workaround), or Option 3 (queue-based)
2. **If Option 1**: Plan database migration, update models, test thoroughly
3. **If Option 2**: Implement `getAllAuthorizedUsers()`, load test with expected user count
4. **If Option 3**: Set up job queues, implement BroadcastAlarmToTenant job

### Testing Requirements

Before production deployment, verify:

1. **Tenant isolation**: Users from Tenant A never receive alarms from Tenant B
2. **Complete coverage**: ALL authorized users receive broadcasts
3. **Performance**: Alarm broadcasts complete within acceptable latency (<1s)
4. **Scalability**: System handles expected alarm volume (e.g., 1000 alarms/hour)
5. **Backward compatibility**: Existing web clients continue to function

### Migration Path

**Phase 1** (Current):
- ✅ Secure WebSocket infrastructure (Reverb configured)
- ✅ Multi-tenant channel authorization
- ✅ Zero cross-tenant leakage
- ❌ Limited user coverage (explicit shares only)

**Phase 2** (Recommended - Add tenant_id):
- Add tenant ownership to data model
- Implement tenant-scoped broadcasting
- Full user coverage
- Support device-less alarms

**Phase 3** (Future enhancements):
- Real-time device status updates
- Task progress notifications
- Firmware deployment broadcasts
- Custom notification preferences per user

## Conclusion

The current WebSocket implementation is **architecturally sound and secure** but has **incomplete coverage** due to missing tenant hierarchy in the data model.

**Recommended Next Step**: Implement **Option 1 (Add Tenant Ownership)** for production-ready, carrier-grade deployment.

**Interim Workaround**: Use **Option 2 (Enhanced User Discovery)** if immediate deployment needed without schema changes, but monitor performance carefully.
