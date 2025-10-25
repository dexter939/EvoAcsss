import Constants from 'expo-constants';

/**
 * App Configuration
 * 
 * Environment-aware configuration for ACS Mobile App
 */

// API Base URL - change based on environment
const getApiUrl = (): string => {
  const { manifest } = Constants;
  
  // Development: use Replit domain or local IP
  if (__DEV__) {
    // If running on Replit, use the Replit domain
    const replitDomain = process.env.REPLIT_DOMAINS;
    if (replitDomain) {
      return `https://${replitDomain}`;
    }
    
    // Otherwise use localhost (for local development)
    // For Android Emulator, use 10.0.2.2
    // For iOS Simulator, use localhost
    return 'http://10.0.2.2:5000';
  }
  
  // Production: use your deployed ACS domain
  return 'https://your-acs-domain.com';
};

export const Config = {
  API_URL: getApiUrl(),
  API_VERSION: 'v1',
  API_TIMEOUT: 30000, // 30 seconds
  
  // API Endpoints
  ENDPOINTS: {
    LOGIN: '/api/auth/login',
    LOGOUT: '/api/auth/logout',
    DEVICES: '/api/v1/devices',
    ALARMS: '/api/v1/alarms',
    DIAGNOSTICS: '/api/v1/diagnostics',
    FIRMWARE: '/api/v1/firmware',
    METRICS: '/api/v1/metrics',
    AUDIT_LOGS: '/api/v1/audit-logs',
  },
  
  // Storage Keys
  STORAGE_KEYS: {
    AUTH_TOKEN: '@acs_auth_token',
    USER_DATA: '@acs_user_data',
    THEME: '@acs_theme',
  },
  
  // App Settings
  REFRESH_INTERVAL: 30000, // 30 seconds
  MAX_RETRY_ATTEMPTS: 3,
  ENABLE_PUSH_NOTIFICATIONS: true,
  
  // Feature Flags
  FEATURES: {
    QR_SCANNER: true,
    GEOLOCATION: true,
    OFFLINE_MODE: true,
    DARK_MODE: true,
  },
};

export default Config;
