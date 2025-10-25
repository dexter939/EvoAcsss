/**
 * TypeScript Type Definitions for ACS Mobile App
 */

// User & Authentication
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'viewer' | 'super-admin';
  created_at: string;
  updated_at: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

// Device Types
export interface Device {
  id: number;
  serial_number: string;
  model_name: string;
  manufacturer: string;
  hardware_version: string;
  software_version: string;
  ip_address: string;
  status: 'online' | 'offline' | 'maintenance';
  last_contact: string;
  customer_id?: number;
  vendor_id?: number;
  created_at: string;
  updated_at: string;
}

export interface DeviceStats {
  total: number;
  online: number;
  offline: number;
  maintenance: number;
}

// Alarm Types
export interface Alarm {
  id: number;
  device_id: number;
  severity: 'critical' | 'major' | 'minor' | 'warning' | 'info';
  type: string;
  message: string;
  acknowledged: boolean;
  acknowledged_by?: number;
  acknowledged_at?: string;
  resolved: boolean;
  resolved_at?: string;
  created_at: string;
  updated_at: string;
  device?: Device;
}

export interface AlarmStats {
  total: number;
  critical: number;
  major: number;
  minor: number;
  warning: number;
  unacknowledged: number;
}

// Diagnostics Types
export interface DiagnosticTest {
  id: number;
  device_id: number;
  test_type: 'ping' | 'traceroute' | 'download' | 'upload';
  status: 'pending' | 'running' | 'completed' | 'failed';
  target?: string;
  result?: any;
  created_at: string;
  updated_at: string;
  device?: Device;
}

// Firmware Types
export interface Firmware {
  id: number;
  version: string;
  vendor_id: number;
  file_path: string;
  file_size: number;
  release_notes?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface ApiResponse<T> {
  data: T;
  message?: string;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  status?: number;
}

// Navigation Types
export type RootStackParamList = {
  Login: undefined;
  Main: undefined;
  DeviceDetails: { deviceId: number };
  DiagnosticTest: { deviceId: number };
  AlarmDetails: { alarmId: number };
  QRScanner: undefined;
};

export type MainTabParamList = {
  Dashboard: undefined;
  Devices: undefined;
  Alarms: undefined;
  Diagnostics: undefined;
  Profile: undefined;
};
