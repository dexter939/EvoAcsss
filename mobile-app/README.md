# ACS Mobile App

**Carrier-Grade Mobile Application** for Auto Configuration Server (ACS) device management.

Built with **React Native** and **Expo** for iOS and Android platforms.

---

## 📱 Features Status

### ✅ Phase 1 - MVP Core (COMPLETED)
- ✅ **Project Setup**: Complete React Native/Expo structure
- ✅ **Authentication**: Token-based login/logout with AsyncStorage
- ✅ **API Services**: Device, Alarm, Diagnostic services ready
- ✅ **Dashboard**: Device and alarm statistics cards
- ✅ **Device List**: Search, filter, and basic device info
- ✅ **Profile Screen**: User info and logout
- ✅ **Navigation**: Stack + Bottom Tabs navigation
- ✅ **Secure Config**: Environment-based API URL/Key management

### 🚧 Phase 2 - Advanced Features (NOT YET IMPLEMENTED)
These screens exist as **placeholders only**:
- ⚠️ **Alarms Screen**: List view only, no real-time polling/streaming
- ⚠️ **Diagnostics Screen**: Stub only, no TR-143 test execution
- ⚠️ **QR Scanner**: Placeholder, requires expo-camera integration
- ⚠️ **Push Notifications**: Config present, handler not implemented
- ⚠️ **Device Details**: Basic stub, needs parameter view/edit
- ⚠️ **Offline Mode**: Not implemented, all data is fetched online

**Phase 2 requires additional development** to implement these features fully.

---

## 🚀 Quick Start

### Prerequisites

- **Node.js** 18+ and npm/yarn
- **Expo CLI**: `npm install -g expo-cli`
- **iOS Simulator** (macOS) or **Android Emulator**

### Installation

```bash
cd mobile-app

# Install dependencies
npm install

# IMPORTANT: Create .env file BEFORE starting
cp .env.example .env
# Edit .env and add your ACS_API_URL and ACS_API_KEY

# Start Expo development server (clear cache to load .env)
npm start -- --clear
```

### Running on Devices

```bash
# iOS Simulator (macOS only)
npm run ios

# Android Emulator
npm run android

# Web browser (for testing)
npm run web
```

### Expo Go App (Physical Devices)

1. Install **Expo Go** from App Store (iOS) or Play Store (Android)
2. Run `npm start` in mobile-app directory
3. Scan QR code with Expo Go app

---

## 🔧 Configuration

### ⚠️ REQUIRED: Environment Setup

**The app will NOT work without proper configuration!**

#### Step 1: Create .env file

```bash
cd mobile-app
cp .env.example .env
```

#### Step 2: Configure .env

```env
# Backend API URL (REQUIRED)
# For Replit: Get from browser address bar when running backend
# Example: https://your-repl-name.replit.dev
ACS_API_URL=https://your-backend-url.com

# Backend API Key (REQUIRED)
# Get this from your ACS administrator or backend settings
ACS_API_KEY=your-api-key-here
```

#### Step 3: Verify app.config.js

The `app.config.js` reads from `.env` automatically via `process.env`:

```javascript
extra: {
  apiUrl: process.env.ACS_API_URL || '',
  apiKey: process.env.ACS_API_KEY || '',
}
```

#### Security Notes

- ✅ **NEVER commit `.env` files** to git (already in .gitignore)
- ✅ **Use different keys** for development/production
- ✅ **Rotate API keys** regularly on backend
- ⚠️ **API keys in mobile apps** can be extracted - consider additional auth layers for production

#### Getting Your Backend URL

1. Start Laravel backend: `php artisan serve --host=0.0.0.0 --port=5000`
2. On Replit: Copy the webview URL (e.g., `https://abc123.replit.dev`)
3. For production: Use your deployed domain
4. Set in `.env` file as `ACS_API_URL`

---

## 📁 Project Structure

```
mobile-app/
├── App.tsx                     # Entry point
├── app.json                    # Expo configuration
├── package.json                # Dependencies
│
├── src/
│   ├── constants/              # App configuration
│   │   └── config.ts           # API URLs, endpoints
│   │
│   ├── types/                  # TypeScript types
│   │   └── index.ts            # Shared interfaces
│   │
│   ├── services/               # API clients
│   │   ├── api.ts              # Base HTTP client
│   │   ├── auth.service.ts     # Authentication
│   │   ├── device.service.ts   # Device management
│   │   ├── alarm.service.ts    # Alarms
│   │   └── diagnostic.service.ts # TR-143 diagnostics
│   │
│   ├── context/                # React Context
│   │   └── AuthContext.tsx     # Authentication state
│   │
│   ├── navigation/             # Navigation structure
│   │   ├── AppNavigator.tsx    # Root navigator
│   │   └── MainTabs.tsx        # Bottom tabs
│   │
│   ├── screens/                # App screens
│   │   ├── LoginScreen.tsx     # Login
│   │   ├── DashboardScreen.tsx # Dashboard
│   │   ├── DevicesScreen.tsx   # Device list
│   │   ├── AlarmsScreen.tsx    # Alarms (stub)
│   │   ├── DiagnosticsScreen.tsx # Diagnostics (stub)
│   │   ├── ProfileScreen.tsx   # User profile
│   │   └── ...                 # Other screens
│   │
│   ├── components/             # Reusable components (future)
│   └── utils/                  # Utility functions (future)
│
└── assets/                     # Images, icons, fonts
```

---

## 🎨 Tech Stack

- **Framework**: React Native 0.74.5
- **SDK**: Expo ~51.0.0
- **Navigation**: React Navigation 6.x
- **HTTP Client**: Axios
- **Storage**: AsyncStorage
- **Icons**: Expo Vector Icons (Ionicons)
- **Language**: TypeScript

### Key Dependencies

```json
{
  "expo": "~51.0.0",
  "react-native": "0.74.5",
  "@react-navigation/native": "^6.1.18",
  "@react-navigation/bottom-tabs": "^6.6.1",
  "axios": "^1.7.7",
  "@react-native-async-storage/async-storage": "1.23.1",
  "expo-camera": "~15.0.16",
  "expo-notifications": "~0.28.18"
}
```

---

## 📱 App Navigation

```
App
├── LoginScreen (unauthenticated)
│
└── MainTabs (authenticated)
    ├── Dashboard
    ├── Devices
    │   └── DeviceDetails
    │       └── DiagnosticTest
    ├── Alarms
    │   └── AlarmDetails
    ├── Diagnostics
    └── Profile
        └── QRScanner
```

---

## 🔐 Authentication Flow

1. **Login**: User enters email/password
2. **Token Storage**: JWT token saved to AsyncStorage
3. **Auto-Login**: Token checked on app start
4. **API Requests**: Token added to Authorization header
5. **Logout**: Clear token and redirect to login

---

## 🏗️ Building for Production

### Android APK/AAB

```bash
# Install EAS CLI
npm install -g eas-cli

# Login to Expo
eas login

# Configure build
eas build:configure

# Build Android APK (for testing)
npm run build:android

# Build Android AAB (for Play Store)
eas build --platform android
```

### iOS IPA

```bash
# Build iOS (requires Apple Developer account)
npm run build:ios

# or
eas build --platform ios
```

---

## 🧪 Testing

### Run on Simulators

```bash
# iOS (macOS only)
npm run ios

# Android
npm run android
```

### Test API Connection

1. Start Laravel backend: `php artisan serve --host=0.0.0.0 --port=5000`
2. Get Replit domain from environment
3. Update `config.ts` if needed
4. Login with test credentials

---

## 📊 API Endpoints Used

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/auth/login` | POST | User authentication |
| `/api/auth/logout` | POST | User logout |
| `/api/v1/devices` | GET | List devices |
| `/api/v1/devices/stats` | GET | Device statistics |
| `/api/v1/alarms` | GET | List alarms |
| `/api/v1/alarms/stats` | GET | Alarm statistics |
| `/api/v1/diagnostics` | GET/POST | TR-143 diagnostics |

---

## 🚧 Roadmap

### Phase 2 - Enhanced Features
- [ ] Complete Alarms screen implementation
- [ ] Complete Diagnostics screen with TR-143 tests
- [ ] QR code scanner for device registration
- [ ] Push notifications setup
- [ ] Device detail screen with parameters

### Phase 3 - Advanced Features
- [ ] Offline mode with data sync
- [ ] Geolocation map with device markers
- [ ] Firmware upgrade management
- [ ] Bulk operations
- [ ] Advanced filtering and sorting

### Phase 4 - Enterprise Features
- [ ] Multi-tenant support
- [ ] Role-based UI customization
- [ ] Report generation
- [ ] Data export (CSV, PDF)
- [ ] Dark mode

---

## 🐛 Troubleshooting

### ⚠️ "API_URL not configured" warning
**Cause**: Missing or empty `.env` file
**Fix**: 
1. Copy `.env.example` to `.env`
2. Set `ACS_API_URL=https://your-backend-url`
3. Restart Expo server (`npm start`)

### "Unable to connect to backend"
**Causes & Fixes**:
- ❌ `.env` file missing → Create it with proper values
- ❌ Backend not running → Start Laravel: `php artisan serve --host=0.0.0.0 --port=5000`
- ❌ Wrong URL in `.env` → Verify URL matches backend address
- ❌ CORS not configured → Add mobile app domain to Laravel CORS config

### "Login failed" or "401 Unauthorized"
**Causes & Fixes**:
- ❌ Wrong API key → Verify `ACS_API_KEY` in `.env` matches backend
- ❌ API key not set → Check `.env` file exists and has `ACS_API_KEY`
- ❌ Invalid credentials → Verify email/password are correct
- ❌ Backend auth endpoint broken → Check `/api/auth/login` works via Postman

### "Network request failed" on physical device
**Causes & Fixes**:
- ❌ Using `localhost` or `10.0.2.2` → These only work on emulators!
- ✅ Use **public URL** in `.env` (Replit domain or ngrok)
- ✅ Ensure device and backend are on same network (local dev)
- ✅ For Replit: Always use the public HTTPS domain

### "Dependencies not installing"
- Delete `node_modules/` and `package-lock.json`
- Run `npm install` again
- Try `npx expo install --fix`

---

## 📝 Development Notes

### Code Style
- TypeScript strict mode enabled
- Functional components with hooks
- Async/await for API calls
- Error handling with try/catch

### State Management
- React Context for global state (Auth)
- Local state with useState
- Future: Consider Redux Toolkit for complex state

### Performance
- FlatList for long lists (virtualization)
- React.memo for expensive components
- Debounced search inputs
- Pull-to-refresh for data updates

---

## 📄 License

Copyright © 2025 ACS Project - Carrier-Grade Device Management

---

## 🤝 Contributing

1. Create feature branch: `git checkout -b feature/amazing-feature`
2. Commit changes: `git commit -m 'Add amazing feature'`
3. Push to branch: `git push origin feature/amazing-feature`
4. Open Pull Request

---

## 📞 Support

For issues or questions:
- Backend API: Check `docs/API_DOCUMENTATION.md`
- Laravel: See main project README
- Expo: https://docs.expo.dev

---

**Built with ❤️ for Carrier-Grade Device Management**
