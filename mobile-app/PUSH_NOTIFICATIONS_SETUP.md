# Push Notifications Setup Guide

Complete guide to enable push notifications for ACS Mobile App.

---

## 📱 Overview

Push notifications are configured in `app.json` but require additional setup for full functionality:

- ✅ **expo-notifications** installed
- ✅ **Permissions configured** in app.json
- ⚠️ **Backend integration** required
- ⚠️ **Expo Push Notification service** setup needed

---

## 🔧 Setup Steps

### 1. Get Expo Push Token

Add to your app (e.g., in `App.tsx` or `ProfileScreen.tsx`):

```typescript
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';

async function registerForPushNotificationsAsync() {
  let token;
  
  if (Device.isDevice) {
    const { status: existingStatus } = await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;
    
    if (existingStatus !== 'granted') {
      const { status } = await Notifications.requestPermissionsAsync();
      finalStatus = status;
    }
    
    if (finalStatus !== 'granted') {
      alert('Failed to get push token for push notification!');
      return;
    }
    
    token = (await Notifications.getExpoPushTokenAsync()).data;
    console.log('Push token:', token);
  } else {
    alert('Must use physical device for Push Notifications');
  }

  return token;
}

// Call this on login or app start
useEffect(() => {
  registerForPushNotificationsAsync().then(token => {
    // Send token to backend
    if (token) {
      // TODO: Save token to user profile via API
      // await apiService.post('/api/auth/register-push-token', { token });
    }
  });
}, []);
```

### 2. Setup Notification Handler

```typescript
// Set notification handler
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

// Listen for notifications
useEffect(() => {
  const subscription = Notifications.addNotificationReceivedListener(notification => {
    console.log('Notification received:', notification);
    // Handle notification (show in-app alert, refresh data, etc.)
  });

  const responseSubscription = Notifications.addNotificationResponseReceivedListener(response => {
    console.log('Notification tapped:', response);
    // Navigate to specific screen based on notification data
    const { screen, alarmId } = response.notification.request.content.data;
    if (screen === 'AlarmDetails') {
      navigation.navigate('AlarmDetails', { alarmId });
    }
  });

  return () => {
    subscription.remove();
    responseSubscription.remove();
  };
}, []);
```

---

## 🔙 Backend Integration

### 1. Add Push Token Storage

In Laravel backend, add migration:

```bash
php artisan make:migration add_push_token_to_users_table
```

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('push_token')->nullable();
    $table->string('push_platform')->nullable(); // 'ios' or 'android'
});
```

### 2. Add API Endpoint

Create `AuthController::registerPushToken()`:

```php
public function registerPushToken(Request $request): JsonResponse
{
    $request->validate([
        'token' => 'required|string',
        'platform' => 'required|in:ios,android',
    ]);

    $request->user()->update([
        'push_token' => $request->token,
        'push_platform' => $request->platform,
    ]);

    return response()->json(['message' => 'Push token registered']);
}
```

Add route:
```php
Route::post('auth/register-push-token', [AuthController::class, 'registerPushToken'])
    ->middleware('auth:sanctum');
```

### 3. Send Push Notifications

When alarm is created/updated:

```php
use Illuminate\Support\Facades\Http;

function sendPushNotification($user, $title, $message, $data = [])
{
    if (!$user->push_token) {
        return;
    }

    $response = Http::post('https://exp.host/--/api/v2/push/send', [
        'to' => $user->push_token,
        'sound' => 'default',
        'title' => $title,
        'body' => $message,
        'data' => $data,
    ]);

    return $response->json();
}

// Usage in AlarmController
$users = User::whereNotNull('push_token')->get();
foreach ($users as $user) {
    sendPushNotification($user, 
        'New Critical Alarm', 
        $alarm->title,
        ['screen' => 'AlarmDetails', 'alarmId' => $alarm->id]
    );
}
```

---

## 📊 Notification Types for ACS

### 1. Critical Alarms
```typescript
{
  title: '🚨 Critical Alarm',
  body: 'Device XYZ offline',
  data: { screen: 'AlarmDetails', alarmId: 123 },
  priority: 'high',
  sound: 'default',
}
```

### 2. Device Status Changes
```typescript
{
  title: 'Device Status Changed',
  body: 'Router ABC is now online',
  data: { screen: 'DeviceDetails', deviceId: 456 },
}
```

### 3. Diagnostic Results
```typescript
{
  title: 'Diagnostic Test Complete',
  body: 'Ping test results ready',
  data: { screen: 'Diagnostics', testId: 789 },
}
```

### 4. Firmware Updates
```typescript
{
  title: 'Firmware Update Available',
  body: 'New version v2.1.0 for CPE Router',
  data: { screen: 'DeviceDetails', deviceId: 456 },
}
```

---

## 🧪 Testing

### Test with Expo Push Notification Tool

1. Get your Expo push token from app
2. Visit: https://expo.dev/notifications
3. Paste token
4. Send test notification

### Test from Backend

```bash
curl -H "Content-Type: application/json" \
     -X POST https://exp.host/--/api/v2/push/send \
     -d '{
       "to": "ExponentPushToken[YOUR_TOKEN_HERE]",
       "title": "Test Notification",
       "body": "This is a test from ACS backend"
     }'
```

---

## 📝 Best Practices

### 1. Batch Notifications
Send to multiple devices efficiently:

```php
$messages = [];
foreach ($users as $user) {
    if ($user->push_token) {
        $messages[] = [
            'to' => $user->push_token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
        ];
    }
}

Http::post('https://exp.host/--/api/v2/push/send', $messages);
```

### 2. Handle Token Expiration

If push fails with "DeviceNotRegistered":

```php
if ($response->json()['data'][0]['status'] === 'error') {
    $user->update(['push_token' => null]);
}
```

### 3. User Preferences

Allow users to disable notifications:

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('notifications_enabled')->default(true);
    $table->json('notification_preferences')->nullable();
});
```

---

## ✅ Implementation Checklist

- [ ] Install expo-notifications (already done)
- [ ] Request push permissions
- [ ] Get Expo push token
- [ ] Send token to backend API
- [ ] Backend: Add push_token column to users table
- [ ] Backend: Create register-push-token endpoint
- [ ] Backend: Implement sendPushNotification helper
- [ ] Backend: Trigger notifications on alarm events
- [ ] Mobile: Handle notification received
- [ ] Mobile: Handle notification tapped
- [ ] Test end-to-end flow

---

## 🔒 Security Notes

- **Never log push tokens** in production
- **Validate tokens** before storing
- **Rate limit** notification sending
- **Allow opt-out** (GDPR compliance)
- **Encrypt sensitive data** in notification payload

---

## 📚 Resources

- Expo Notifications: https://docs.expo.dev/push-notifications/overview/
- Expo Push Tool: https://expo.dev/notifications
- Laravel Notifications: https://laravel.com/docs/notifications

---

**Status**: ⚠️ **Setup Required** - Follow steps above to enable push notifications.
