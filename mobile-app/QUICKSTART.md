# ACS Mobile App - Quick Start Guide

**Complete setup in 5 minutes!** 🚀

---

## ✅ Pre-requisites

- Node.js 18+ installed
- iOS/Android device OR emulator
- Expo Go app (for physical devices)

---

## 🚀 Setup

### 1. Install Dependencies

```bash
cd mobile-app
npm install
```

### 2. Configure Backend URL

The `.env` file is already created with the current backend URL:

```env
ACS_API_URL=https://af033280-2a00-40b8-aa0d-5550f59fd4d2-00-6y2330nmtgv4.janeway.replit.dev
ACS_API_KEY=acs-dev-test-key-2024
```

**Note**: If backend URL changes, update `ACS_API_URL` in `.env`.

### 3. Start Expo Development Server

```bash
npm start -- --clear
```

**Output**: QR code will appear in terminal.

---

## 📱 Run on Device

### Option A: Physical Device (Recommended)

1. **Install Expo Go**:
   - iOS: https://apps.apple.com/app/expo-go/id982107779
   - Android: https://play.google.com/store/apps/details?id=host.exp.exponent

2. **Scan QR Code**:
   - iOS: Open Camera app → Scan QR code
   - Android: Open Expo Go app → Tap "Scan QR Code"

3. **App loads automatically!**

### Option B: iOS Simulator (macOS only)

```bash
npm run ios
```

### Option C: Android Emulator

```bash
npm run android
```

---

## 🔐 Test Login

### Credentials:
- **Email**: `admin@acs.local`
- **Password**: `password`

### Expected Flow:
1. App opens to Login screen
2. Enter email + password
3. Tap "Login" button
4. Redirects to Dashboard
5. Shows device/alarm statistics

---

## 🐛 Troubleshooting

### "API_URL not configured" warning

**Fix**:
```bash
# Make sure .env exists
ls -la .env

# If missing, create it
cp .env.example .env

# Restart Expo with cache clear
npm start -- --clear
```

### "Unable to connect to backend"

**Fix**:
1. Check backend is running (ACS Server workflow)
2. Verify `.env` has correct URL
3. Test backend manually:
   ```bash
   curl https://YOUR-BACKEND-URL/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@acs.local","password":"password"}'
   ```

### "Login failed" or "401 Unauthorized"

**Fix**:
1. Verify credentials: `admin@acs.local` / `password`
2. Check backend logs for errors
3. Ensure user exists in database

### "Network request failed" on physical device

**Fix**:
- ✅ Use **public HTTPS URL** in `.env` (Replit domain)
- ❌ Don't use `localhost` or `10.0.2.2` (emulator-only)

---

## 📊 What You'll See

### 1. Login Screen
- ACS logo
- Email + Password inputs
- Login button
- "v1.0.0 - Carrier Grade" footer

### 2. Dashboard
- Device statistics cards:
  - Total
  - Online
  - Offline
  - Maintenance
- Alarm statistics cards:
  - Critical
  - Major
  - Minor
  - Unacknowledged
- Pull-to-refresh functionality

### 3. Devices Screen
- Search bar
- Device list with:
  - Serial number
  - Model name
  - IP address
  - Status badge
- Tap device for details (stub)

### 4. Profile Screen
- User info (name, email, role)
- Logout button

### 5. Other Screens (Stubs)
- Alarms (placeholder)
- Diagnostics (placeholder)
- QR Scanner (placeholder)

---

## 🎯 Next Steps

### Phase 2 Features (Not Yet Implemented)
- Real-time alarm monitoring
- TR-143 diagnostic execution
- QR code scanner
- Push notifications
- Offline mode

### Add Test Data

To populate dashboard with test data:

```bash
# Create test device
php artisan tinker --execute="
  \$device = new \App\Models\Device();
  \$device->serial_number = 'TEST001';
  \$device->model_name = 'Test Router';
  \$device->manufacturer = 'TestCorp';
  \$device->hardware_version = '1.0';
  \$device->software_version = '2.0';
  \$device->ip_address = '192.168.1.1';
  \$device->status = 'online';
  \$device->save();
  echo 'Device created';
"
```

Refresh mobile app to see new device.

---

## 📝 Development Tips

### Live Reload
- Changes to `.tsx` files reload automatically
- Changes to `.env` require restart:
  ```bash
  npm start -- --clear
  ```

### Debugging
- Shake device → "Debug Remote JS"
- Browser console shows logs
- React Native Debugger for advanced debugging

### Testing API
- Check mobile app console logs
- Backend logs show API requests
- Use `console.log()` in services/screens

---

## ✅ Success Checklist

- [ ] Backend running (ACS Server workflow)
- [ ] `.env` file configured
- [ ] `npm install` completed
- [ ] Expo server started
- [ ] App loaded on device/emulator
- [ ] Login successful
- [ ] Dashboard shows statistics
- [ ] Devices screen loads

---

## 🎉 You're Ready!

The mobile app MVP is now running! Test the login flow and explore the dashboard.

For questions or issues, check:
- `README.md` - Full documentation
- `SETUP_GUIDE.md` - Detailed setup instructions
- `TEST_RESULTS.md` - Backend API test results

**Happy coding! 📱✨**
