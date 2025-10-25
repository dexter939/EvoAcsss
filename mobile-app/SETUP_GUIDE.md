# ACS Mobile App - Setup Guide

Complete setup instructions for development and production deployment.

---

## 🚀 Quick Start (5 Minutes)

### 1. Prerequisites

Install these first:

```bash
# Node.js 18+ (check version)
node --version

# Install Expo CLI globally
npm install -g expo-cli

# Install EAS CLI (for builds)
npm install -g eas-cli
```

### 2. Configure Environment FIRST

**⚠️ Do this BEFORE running npm install!**

```bash
cd mobile-app

# Copy example env file
cp .env.example .env

# Edit .env with your values
nano .env  # or use your preferred editor
```

Required values in `.env`:

```env
ACS_API_URL=https://your-backend.replit.dev
ACS_API_KEY=your-api-key-here
```

### 4. Start Development Server

```bash
npm start
```

### 5. Run on Device/Emulator

**Option A: Physical Device (Expo Go)**
1. Install "Expo Go" app from App Store/Play Store
2. Scan QR code from terminal
3. App loads on your device

**Option B: iOS Simulator** (macOS only)
```bash
npm run ios
```

**Option C: Android Emulator**
```bash
npm run android
```

---

## 🔑 Getting API Credentials

### Backend URL

**For Replit Development:**
1. Open your ACS backend Replit
2. Click the "Run" button
3. Copy the webview URL (e.g., `https://abc123-def456.replit.dev`)
4. Set in `.env` as `ACS_API_URL=https://abc123-def456.replit.dev`

**For Production:**
1. Use your deployed domain
2. Example: `ACS_API_URL=https://acs.yourcompany.com`

### API Key

**From Backend Admin:**
1. Login to ACS web interface as admin
2. Go to Settings → API Keys
3. Generate new key for mobile app
4. Copy key to `.env` as `ACS_API_KEY=...`

**From Backend Code:**
```bash
# SSH into backend
php artisan tinker

# Generate API key
>>> \Illuminate\Support\Str::random(32);
```

---

## 📱 Testing on Physical Devices

### iOS (iPhone/iPad)

**Requirements:**
- iPhone/iPad with iOS 13+
- "Expo Go" app installed
- Same WiFi network as development machine

**Steps:**
1. Run `npm start` in mobile-app directory
2. Open Expo Go app
3. Tap "Scan QR Code"
4. Scan QR from terminal
5. App loads automatically

**Important:** Set `ACS_API_URL` to **public URL** (not localhost!):
```env
# ✅ CORRECT (works on physical device)
ACS_API_URL=https://your-repl.replit.dev

# ❌ WRONG (only works on simulator)
ACS_API_URL=http://localhost:5000
```

### Android Phone/Tablet

Same as iOS, but Android device only needs to be on same network OR you can scan QR with Expo Go app directly.

---

## 🏗️ Building for Production

### Android APK/AAB

#### One-Time Setup

```bash
# Login to Expo account
eas login

# Configure EAS build
eas build:configure
```

#### Build APK (Internal Testing)

```bash
# APK for direct installation
eas build --platform android --profile preview
```

Download APK and install on device for testing.

#### Build AAB (Google Play)

```bash
# AAB for Play Store submission
eas build --platform android --profile production
```

Upload AAB to Google Play Console.

### iOS IPA

**Requirements:**
- Apple Developer Account ($99/year)
- Signing certificate configured in Expo

```bash
# Build IPA for TestFlight/App Store
eas build --platform ios --profile production
```

Upload IPA to App Store Connect.

---

## 🔒 Production Security Checklist

### Before Deploying to Production:

- [ ] **Environment Variables Set**
  - [ ] `ACS_API_URL` points to production backend
  - [ ] `ACS_API_KEY` is production key (not dev key!)
  - [ ] Never commit `.env` file to git

- [ ] **Backend Security**
  - [ ] HTTPS enabled (no HTTP)
  - [ ] CORS configured for mobile app
  - [ ] API key rotation policy in place
  - [ ] Rate limiting enabled

- [ ] **App Security**
  - [ ] No hardcoded credentials in code
  - [ ] Certificate pinning (optional, advanced)
  - [ ] Code obfuscation for release builds
  - [ ] Disable debug logs in production

- [ ] **Testing**
  - [ ] Test login flow
  - [ ] Test API connectivity
  - [ ] Test offline behavior
  - [ ] Test on multiple devices/OS versions

---

## 🌍 Environment Management

### Multiple Environments

Create separate `.env` files:

```bash
.env.development    # Local dev
.env.staging        # Staging server
.env.production     # Production
```

Load based on build profile in `app.config.js`:

```javascript
const ENV = process.env.APP_ENV || 'development';
require('dotenv').config({ path: `.env.${ENV}` });
```

### EAS Build Secrets

For production builds, set secrets via EAS:

```bash
# Set secret for production builds
eas secret:create --scope project --name ACS_API_URL --value https://prod-api.com
eas secret:create --scope project --name ACS_API_KEY --value prod-key-123
```

These override local `.env` during EAS builds.

---

## 🧪 Testing Backend Connection

### Quick Test Script

Add to `package.json`:

```json
{
  "scripts": {
    "test:api": "node scripts/test-api.js"
  }
}
```

Create `scripts/test-api.js`:

```javascript
const axios = require('axios');
require('dotenv').config();

async function testAPI() {
  const apiUrl = process.env.ACS_API_URL;
  const apiKey = process.env.ACS_API_KEY;

  console.log('Testing API Connection...');
  console.log('API URL:', apiUrl);

  try {
    const response = await axios.get(`${apiUrl}/api/v1/devices`, {
      headers: { 'X-API-Key': apiKey }
    });
    console.log('✅ SUCCESS! Connected to backend');
    console.log('Devices found:', response.data.meta?.total || 0);
  } catch (error) {
    console.log('❌ FAILED! Error:', error.message);
  }
}

testAPI();
```

Run: `npm run test:api`

---

## 📞 Support

### Common Issues

1. **"Expo Go not loading"**
   - Check WiFi connection
   - Restart Expo server
   - Clear Expo cache: `expo start -c`

2. **"Build failed"**
   - Update dependencies: `npm update`
   - Clear cache: `rm -rf node_modules && npm install`
   - Check EAS build logs

3. **"API not reachable"**
   - Verify backend is running
   - Check `.env` configuration
   - Test with `curl` or Postman first

### Getting Help

- **Expo Docs**: https://docs.expo.dev
- **React Native Docs**: https://reactnative.dev
- **Backend API**: See Laravel documentation

---

**Built for ACS Carrier-Grade Device Management 🚀**
