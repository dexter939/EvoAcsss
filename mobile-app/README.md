# ACS Mobile App

**Carrier-Grade Mobile Application** for Auto Configuration Server (ACS) device management.

Built with **React Native** and **Expo** for iOS and Android platforms.

---

## 📱 Features

### ✅ Phase 1 - Core (Implemented)
- **Authentication**: Token-based login/logout
- **Dashboard**: Real-time device and alarm statistics
- **Device Management**: List, search, and filter devices
- **Profile**: User info and logout

### 🚧 Phase 2 - Advanced (Coming Soon)
- **Alarms Management**: Real-time alarm monitoring and acknowledgment
- **TR-143 Diagnostics**: Ping, Traceroute, Download, Upload tests
- **QR Code Scanner**: Device registration via QR codes
- **Push Notifications**: Real-time alarm notifications
- **Offline Mode**: Work without internet connection
- **Geolocation Map**: Device locations on map

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

# Start Expo development server
npm start
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

### API Backend

Edit `src/constants/config.ts`:

```typescript
// Development
const replitDomain = process.env.REPLIT_DOMAINS;
return `https://${replitDomain}`; // Auto-detects Replit domain

// Production
return 'https://your-acs-domain.com';
```

### Environment Variables

Create `.env` file:

```env
# Replit domain (auto-detected)
REPLIT_DOMAINS=your-repl.replit.dev

# API Key (matches Laravel backend)
ACS_API_KEY=your-api-key-here
```

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

### "Unable to connect to backend"
- Check `config.ts` API_URL matches backend URL
- Verify backend is running on port 5000
- For Android emulator, use `10.0.2.2` instead of `localhost`
- For iOS simulator, `localhost` works

### "Login failed"
- Verify backend API key in headers
- Check backend `/api/auth/login` endpoint exists
- Ensure CORS is configured on backend
- Check network console logs

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
