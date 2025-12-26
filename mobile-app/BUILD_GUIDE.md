# ACS Mobile App - Build Guide

## Prerequisites

1. **Node.js 18+** installed
2. **Expo CLI** installed globally: `npm install -g expo-cli`
3. **EAS CLI** installed globally: `npm install -g eas-cli`
4. **Expo Account**: Sign up at https://expo.dev

## Setup

### 1. Install Dependencies

```bash
cd mobile-app
npm install
```

### 2. Configure Environment

Copy the environment template and configure:

```bash
cp .env.example .env
```

Edit `.env`:
```env
ACS_API_URL=https://your-acs-server.com
ACS_API_KEY=your-api-key-here
DEBUG=false
```

### 3. Login to Expo

```bash
eas login
```

## Development

### Run on Expo Go (Development)

```bash
npm start
```

Scan the QR code with Expo Go app on your phone.

### Run on iOS Simulator

```bash
npm run ios
```

### Run on Android Emulator

```bash
npm run android
```

## Production Builds

### Android APK (Internal Testing)

```bash
eas build --platform android --profile preview
```

This creates an APK for internal distribution.

### Android AAB (Play Store)

```bash
eas build --platform android --profile production
```

This creates an Android App Bundle for Google Play Store submission.

### iOS Build (App Store)

```bash
eas build --platform ios --profile production
```

**Requirements**:
- Apple Developer Account ($99/year)
- Configure `appleId`, `ascAppId`, `appleTeamId` in `eas.json`

### iOS Simulator Build

```bash
eas build --platform ios --profile preview
```

## EAS Configuration

The `eas.json` file contains three build profiles:

| Profile | Distribution | Use Case |
|---------|--------------|----------|
| `development` | Internal | Development builds with dev client |
| `preview` | Internal | Testing builds (APK for Android, Simulator for iOS) |
| `production` | Store | App Store / Play Store releases |

### Setting Environment Variables

For production builds, set secrets in EAS:

```bash
eas secret:create --name ACS_API_URL --value "https://your-production-url.com" --scope project
eas secret:create --name ACS_API_KEY --value "your-production-key" --scope project
```

## Store Submission

### Google Play Store

1. Build production AAB:
   ```bash
   eas build --platform android --profile production
   ```

2. Submit to Play Store:
   ```bash
   eas submit --platform android --profile production
   ```

**Requirements**:
- Google Play Developer Account ($25 one-time)
- `google-services.json` service account key

### Apple App Store

1. Build production IPA:
   ```bash
   eas build --platform ios --profile production
   ```

2. Submit to App Store:
   ```bash
   eas submit --platform ios --profile production
   ```

**Requirements**:
- Apple Developer Account ($99/year)
- App Store Connect app created

## App Icons & Splash Screen

Replace these files in `assets/`:

| File | Size | Purpose |
|------|------|---------|
| `icon.png` | 1024x1024 | App icon |
| `adaptive-icon.png` | 1024x1024 | Android adaptive icon |
| `splash.png` | 1284x2778 | Splash screen |
| `favicon.png` | 48x48 | Web favicon |
| `notification-icon.png` | 96x96 | Push notification icon (Android) |

## Troubleshooting

### Build Fails with "Missing Credentials"

```bash
eas credentials
```

Follow prompts to configure iOS/Android credentials.

### "expo-cli is not recognized"

```bash
npm install -g expo-cli eas-cli
```

### "Project not linked"

```bash
eas init
```

### Check Build Status

```bash
eas build:list
```

## Version Management

Update version in `app.config.js`:

```javascript
version: '1.1.0',  // Semantic version
```

For auto-increment on builds:
```json
"production": {
  "autoIncrement": true
}
```
