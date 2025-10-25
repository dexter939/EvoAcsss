import Constants from 'expo-constants';

/**
 * App Configuration
 * 
 * Environment-aware configuration for ACS Mobile App
 * 
 * IMPORTANT: API URL and API Key MUST be configured via:
 * 1. Development: Create .env file with ACS_API_URL and ACS_API_KEY
 * 2. Production: Set EAS build secrets or app.config.js extra values
 * 
 * Never hardcode production URLs or API keys in this file!
 */

// Get API URL from Expo Constants (set via app.config.js)
const getApiUrl = (): string => {
  // Try multiple sources (handles both dev and production builds)
  const configuredUrl = 
    Constants.expoConfig?.extra?.apiUrl ||     // Expo Go / dev
    Constants.manifest?.extra?.apiUrl ||        // Legacy
    Constants.manifest2?.extra?.expoClient?.extra?.apiUrl;  // EAS builds
  
  if (configuredUrl) {
    return configuredUrl;
  }
  
  // Fallback for development - prompt user to configure
  if (__DEV__) {
    console.warn(
      '⚠️  API_URL not configured! Please set ACS_API_URL in .env file.\n' +
      'Example: ACS_API_URL=https://your-repl.replit.dev\n' +
      'Then restart: expo start -c'
    );
    // Return empty string to fail fast and show error
    return '';
  }
  
  // Production should always have configured URL
  throw new Error('API_URL not configured. Set ACS_API_URL environment variable.');
};

// Get API Key from Expo Constants (NEVER hardcode)
const getApiKey = (): string => {
  // Try multiple sources (handles both dev and production builds)
  const configuredKey = 
    Constants.expoConfig?.extra?.apiKey ||     // Expo Go / dev
    Constants.manifest?.extra?.apiKey ||        // Legacy
    Constants.manifest2?.extra?.expoClient?.extra?.apiKey;  // EAS builds
  
  if (configuredKey) {
    return configuredKey;
  }
  
  // Fail fast if not configured
  if (__DEV__) {
    console.warn(
      '⚠️  API_KEY not configured! Please set ACS_API_KEY in .env file.\n' +
      'Get your API key from the ACS backend settings.\n' +
      'Then restart: expo start -c'
    );
    return '';
  }
  
  throw new Error('API_KEY not configured. Set ACS_API_KEY environment variable.');
};

export const Config = {
  API_URL: getApiUrl(),
  API_KEY: getApiKey(),
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
