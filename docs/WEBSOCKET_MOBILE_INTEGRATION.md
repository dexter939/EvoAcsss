# WebSocket Mobile Integration Guide
**React Native Mobile App - Real-time Updates with Laravel Reverb**

## Overview
This guide explains how to integrate Laravel Reverb WebSocket into the React Native mobile application for real-time alarm notifications, device status updates, and task progress monitoring.

## Backend Configuration

### 1. Laravel Reverb Setup (COMPLETED)
- **Package**: `laravel/reverb` v1.6.0
- **Server**: Running on port 8080 (workflow configured)
- **Driver**: Reverb (configured in `.env`)
- **Broadcasting**: Enabled with ShouldBroadcast events

### 2. Broadcasting Channels (COMPLETED - December 2025)
Private channels configured in `routes/channels.php` with **carrier-grade multi-tenant isolation**:

```php
// Tenant-wide alarms channel (NEW - December 2025)
// All users within a tenant receive broadcasts
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    if ($user->isSuperAdmin()) return true;
    return $user->tenant_id === (int) $tenantId;
});

// Tenant-wide presence channel (shows online users)
Broadcast::channel('tenant.{tenantId}.presence', function ($user, $tenantId) {
    if ($user->tenant_id === (int) $tenantId) {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
    return false;
});

// Tenant severity-filtered channel
Broadcast::channel('tenant.{tenantId}.alarms.{severity}', function ($user, $tenantId, $severity) {
    return $user->tenant_id === (int) $tenantId;
});

// User-specific alarms channel (backward compatible)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Device-specific with multi-tenant access control
Broadcast::channel('device.{deviceSerial}', function ($user, $deviceSerial) {
    if ($user->isSuperAdmin()) return true;
    $device = CpeDevice::where('serial_number', $deviceSerial)->first();
    return $device && $user->canAccessDevice($device);
});
```

**Multi-Tenant Security - Production-Safe Implementation**:
1. ✅ **Tenant Channels**: Broadcast to all users in tenant via `tenant.{tenantId}` channel
2. ✅ **Severity Filtering**: Subscribe to specific severity via `tenant.{tenantId}.alarms.{severity}`
3. ✅ **Presence Tracking**: See online users via `tenant.{tenantId}.presence` channel
4. ✅ **User-Scoped Fallback**: Backward compatible `user.{userId}` channels still work
5. ✅ **Zero Cross-Tenant Leakage**: Strict tenant_id validation on all channels
6. **Result**: Carrier-grade multi-tenant isolation with flexible subscription options

### 3. Broadcast Events (UPDATED - December 2025)
- **AlarmCreated**: Dispatched when new alarm is raised (already integrated in `AlarmService`)
- **Event Channels** (multi-tier):
  - `tenant.{tenantId}` - All users in tenant receive alarms
  - `tenant.{tenantId}.alarms.{severity}` - Severity-filtered subscription
  - `user.{userId}` - Backward compatible user-specific channel
- **Event Name**: `alarm.created`
- **Multi-Tenant Isolation**: Broadcasts to tenant channel + user channels for complete coverage
- **Payload** (backward compatible):
  ```json
  {
    "id": 123,
    "tenant_id": 1,
    "device_id": 456,
    "device_serial": "SN123456",
    "severity": "critical",
    "message": "Device Offline: SN123456",
    "title": "Device Offline: SN123456",
    "description": "Device has gone offline. Last seen: 2 hours ago",
    "status": "active",
    "category": "connectivity",
    "alarm_type": "device_offline",
    "raised_at": "2025-11-01T12:00:00.000Z",
    "created_at": "2025-11-01T12:00:00.000Z"
  }
  ```
  
**Note**: `tenant_id` added to payload. Both `message` (legacy) and `title`/`description` (new) included for backward compatibility.

## Mobile App Integration (React Native)

### Phase 3 Implementation Plan

#### Step 1: Install WebSocket Client Library
```bash
npm install laravel-echo pusher-js
# or
yarn add laravel-echo pusher-js
```

#### Step 2: Configure Echo Client
Create `mobile/src/services/websocket.ts`:

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Enable Pusher client for Reverb
window.Pusher = Pusher;

export const initializeWebSocket = (authToken: string) => {
  return new Echo({
    broadcaster: 'reverb',
    key: 'acs-local-key',
    wsHost: process.env.EXPO_PUBLIC_WS_HOST || 'localhost',
    wsPort: process.env.EXPO_PUBLIC_WS_PORT || 8080,
    wssPort: process.env.EXPO_PUBLIC_WS_PORT || 8080,
    forceTLS: process.env.EXPO_PUBLIC_WS_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${process.env.EXPO_PUBLIC_API_URL}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
      },
    },
  });
};
```

#### Step 3: Update Environment Variables
Add to `mobile/.env`:

```env
EXPO_PUBLIC_WS_HOST=your-replit-domain.repl.co
EXPO_PUBLIC_WS_PORT=8080
EXPO_PUBLIC_WS_SCHEME=https
```

#### Step 4: Subscribe to Channels in Screens
Replace polling with WebSocket subscription in `AlarmsScreen.tsx`:

```typescript
import { useEffect, useState } from 'react';
import { initializeWebSocket } from '../services/websocket';
import AsyncStorage from '@react-native-async-storage/async-storage';

export default function AlarmsScreen() {
  const [alarms, setAlarms] = useState([]);
  const [echo, setEcho] = useState(null);
  const [userId, setUserId] = useState(null);

  useEffect(() => {
    const setupWebSocket = async () => {
      const token = await AsyncStorage.getItem('userToken');
      const userIdStored = await AsyncStorage.getItem('userId');
      setUserId(userIdStored);
      
      const echoInstance = initializeWebSocket(token);
      
      // Subscribe to user-specific private channel (TENANT-SCOPED)
      // SECURITY: Only receive alarms for devices you have access to
      echoInstance
        .private(`user.${userIdStored}`)
        .listen('.alarm.created', (event) => {
          console.log('New alarm received:', event);
          setAlarms((prev) => [event, ...prev]);
          // Show notification
          showNotification(event);
        });
      
      setEcho(echoInstance);
    };

    setupWebSocket();

    return () => {
      if (echo) {
        echo.disconnect();
      }
    };
  }, []);

  return (
    // Your UI components
  );
}
```

#### Step 5: Implement Offline Sync (Task 13)
Create `mobile/src/services/offlineSync.ts`:

```typescript
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';

interface PendingAction {
  id: string;
  type: 'acknowledge' | 'clear' | 'diagnostic';
  payload: any;
  timestamp: number;
}

export class OfflineSyncService {
  private static QUEUE_KEY = 'pending_actions_queue';

  // Queue action for later sync
  static async queueAction(action: PendingAction): Promise<void> {
    const queue = await this.getQueue();
    queue.push(action);
    await AsyncStorage.setItem(this.QUEUE_KEY, JSON.stringify(queue));
  }

  // Get pending actions
  private static async getQueue(): Promise<PendingAction[]> {
    const data = await AsyncStorage.getItem(this.QUEUE_KEY);
    return data ? JSON.parse(data) : [];
  }

  // Sync pending actions when online
  static async syncPendingActions(apiClient: any): Promise<void> {
    const queue = await this.getQueue();
    const failed: PendingAction[] = [];

    for (const action of queue) {
      try {
        await this.executeAction(action, apiClient);
      } catch (error) {
        console.error('Failed to sync action:', action, error);
        failed.push(action);
      }
    }

    // Keep only failed actions
    await AsyncStorage.setItem(this.QUEUE_KEY, JSON.stringify(failed));
  }

  private static async executeAction(action: PendingAction, apiClient: any) {
    switch (action.type) {
      case 'acknowledge':
        await apiClient.acknowledgeAlarm(action.payload.alarmId);
        break;
      case 'clear':
        await apiClient.clearAlarm(action.payload.alarmId, action.payload.resolution);
        break;
      // Add other action types
    }
  }

  // Monitor network and auto-sync
  static setupAutoSync(apiClient: any): void {
    NetInfo.addEventListener((state) => {
      if (state.isConnected) {
        this.syncPendingActions(apiClient);
      }
    });
  }
}
```

#### Step 6: Advanced Filtering UI (Task 14)
Create `mobile/src/components/AdvancedFilterModal.tsx`:

```typescript
import React, { useState } from 'react';
import { Modal, View, Text, TouchableOpacity } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';

interface FilterOptions {
  severities: string[];
  dateRange: { start: Date; end: Date };
  statuses: string[];
  devices: string[];
}

export default function AdvancedFilterModal({ visible, onClose, onApply }) {
  const [filters, setFilters] = useState<FilterOptions>({
    severities: [],
    dateRange: { start: new Date(), end: new Date() },
    statuses: ['active'],
    devices: [],
  });

  const toggleSeverity = (severity: string) => {
    setFilters((prev) => ({
      ...prev,
      severities: prev.severities.includes(severity)
        ? prev.severities.filter((s) => s !== severity)
        : [...prev.severities, severity],
    }));
  };

  return (
    <Modal visible={visible} animationType="slide">
      <View style={{ padding: 20 }}>
        <Text style={{ fontSize: 20, fontWeight: 'bold' }}>Advanced Filters</Text>
        
        {/* Severity Selection */}
        <Text style={{ marginTop: 20 }}>Severity</Text>
        {['critical', 'major', 'minor', 'warning', 'info'].map((severity) => (
          <TouchableOpacity
            key={severity}
            onPress={() => toggleSeverity(severity)}
            style={{
              padding: 10,
              backgroundColor: filters.severities.includes(severity) ? '#007AFF' : '#f0f0f0',
              marginVertical: 5,
              borderRadius: 8,
            }}
          >
            <Text
              style={{
                color: filters.severities.includes(severity) ? 'white' : 'black',
              }}
            >
              {severity.toUpperCase()}
            </Text>
          </TouchableOpacity>
        ))}

        {/* Date Range Picker */}
        <Text style={{ marginTop: 20 }}>Date Range</Text>
        <DateTimePicker
          value={filters.dateRange.start}
          mode="date"
          onChange={(event, date) =>
            setFilters((prev) => ({
              ...prev,
              dateRange: { ...prev.dateRange, start: date || new Date() },
            }))
          }
        />

        {/* Apply/Cancel Buttons */}
        <TouchableOpacity
          onPress={() => onApply(filters)}
          style={{
            backgroundColor: '#007AFF',
            padding: 15,
            borderRadius: 8,
            marginTop: 30,
          }}
        >
          <Text style={{ color: 'white', textAlign: 'center', fontWeight: 'bold' }}>
            Apply Filters
          </Text>
        </TouchableOpacity>
      </View>
    </Modal>
  );
}
```

#### Step 7: Bulk Operations UI (Task 15)
Create `mobile/src/components/BulkActionsBar.tsx`:

```typescript
import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Icon from 'react-native-vector-icons/FontAwesome5';

interface BulkActionsBarProps {
  selectedCount: number;
  onAcknowledgeAll: () => void;
  onClearAll: () => void;
  onDeselectAll: () => void;
}

export default function BulkActionsBar({
  selectedCount,
  onAcknowledgeAll,
  onClearAll,
  onDeselectAll,
}: BulkActionsBarProps) {
  if (selectedCount === 0) return null;

  return (
    <View style={styles.container}>
      <Text style={styles.countText}>{selectedCount} selected</Text>
      
      <View style={styles.actions}>
        <TouchableOpacity onPress={onAcknowledgeAll} style={styles.actionButton}>
          <Icon name="check" size={16} color="white" />
          <Text style={styles.actionText}>Acknowledge</Text>
        </TouchableOpacity>
        
        <TouchableOpacity onPress={onClearAll} style={[styles.actionButton, { backgroundColor: '#dc3545' }]}>
          <Icon name="times" size={16} color="white" />
          <Text style={styles.actionText}>Clear</Text>
        </TouchableOpacity>
        
        <TouchableOpacity onPress={onDeselectAll} style={styles.cancelButton}>
          <Text style={styles.cancelText}>Cancel</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: '#343a40',
    padding: 15,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  countText: {
    color: 'white',
    fontWeight: 'bold',
  },
  actions: {
    flexDirection: 'row',
    gap: 10,
  },
  actionButton: {
    backgroundColor: '#007AFF',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  actionText: {
    color: 'white',
    fontWeight: '600',
  },
  cancelButton: {
    paddingHorizontal: 15,
    paddingVertical: 8,
  },
  cancelText: {
    color: 'white',
  },
});
```

## Security Considerations

### 1. Multi-Tenant Authorization
- All private channels enforce user-device access control
- Super admins can access all channels
- Regular users restricted to authorized devices
- Department-based isolation supported

### 2. Authentication
- WebSocket auth uses Laravel Sanctum tokens
- Auth endpoint: `/broadcasting/auth`
- Token passed in `Authorization` header
- Token stored securely in AsyncStorage

### 3. Network Security
- Use TLS/WSS in production (EXPO_PUBLIC_WS_SCHEME=https)
- Validate all incoming event payloads
- Handle connection errors gracefully
- Implement reconnection logic

## Performance Optimization

### 1. Connection Management
- Disconnect WebSocket when app goes to background
- Reconnect when app becomes active
- Implement exponential backoff for reconnection
- Limit number of active subscriptions

### 2. Event Handling
- Debounce rapid event streams
- Batch UI updates to reduce renders
- Use React.memo for list items
- Implement virtual scrolling for long lists

### 3. Offline Support
- Queue actions when offline
- Auto-sync when connection restored
- Show offline indicators
- Cache critical data locally

## Testing

### 1. Backend Testing
```bash
# Test Reverb server
php artisan reverb:start --debug

# Trigger test alarm
php artisan tinker
> $alarm = \App\Models\Alarm::factory()->create();
> event(new \App\Events\AlarmCreated($alarm));
```

### 2. Mobile Testing
```bash
# Test WebSocket connection
npx expo start
# Enable debug mode in app
# Monitor console for WebSocket events
```

## Troubleshooting

### Common Issues

1. **Connection Refused**
   - Verify Reverb server is running: `php artisan reverb:start`
   - Check firewall rules for port 8080
   - Verify WS_HOST points to correct domain

2. **Auth Errors**
   - Verify Sanctum token is valid
   - Check `/broadcasting/auth` endpoint returns 200
   - Ensure token has correct permissions

3. **Channel Authorization Failed**
   - Verify user has device access permissions
   - Check `canAccessDevice()` method logic
   - Review channel authorization callbacks

4. **Events Not Received**
   - Check event is implementing `ShouldBroadcast`
   - Verify `broadcastOn()` returns correct channels
   - Check event is dispatched: `event(new AlarmCreated($alarm))`

## Production Deployment

### Backend
1. Set production WebSocket URL in `.env`:
   ```env
   REVERB_HOST=your-production-domain.com
   REVERB_SCHEME=https
   ```

2. Run Reverb with supervisor/systemd:
   ```ini
   [program:reverb]
   command=php /path/to/artisan reverb:start --host=0.0.0.0 --port=8080
   autostart=true
   autorestart=true
   ```

### Mobile
1. Update production config in `mobile/.env.production`:
   ```env
   EXPO_PUBLIC_WS_HOST=your-production-domain.com
   EXPO_PUBLIC_WS_PORT=443
   EXPO_PUBLIC_WS_SCHEME=https
   ```

2. Build production app:
   ```bash
   eas build --platform all --profile production
   ```

## Next Steps

1. ✅ **Backend Complete**: Reverb installed, events configured, channels protected
2. 🚧 **Mobile Phase 3 Tasks**:
   - [ ] Install Laravel Echo + Pusher.js in mobile app
   - [ ] Configure WebSocket client with auth
   - [ ] Replace polling with WebSocket subscriptions
   - [ ] Implement offline sync with AsyncStorage
   - [ ] Create advanced filtering UI
   - [ ] Build bulk operations interface
   - [ ] Add push notifications support
   - [ ] Performance testing and optimization

## References
- [Laravel Broadcasting](https://laravel.com/docs/11.x/broadcasting)
- [Laravel Reverb](https://laravel.com/docs/11.x/reverb)
- [Laravel Echo](https://github.com/laravel/echo)
- [React Native AsyncStorage](https://react-native-async-storage.github.io/async-storage/)
- [Expo Push Notifications](https://docs.expo.dev/push-notifications/overview/)
