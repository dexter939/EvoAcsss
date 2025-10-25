import axios, { AxiosInstance, AxiosError, AxiosRequestConfig } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Config from '../constants/config';
import { ApiError } from '../types';

/**
 * API Service
 * 
 * Centralized HTTP client for ACS backend communication
 */

class ApiService {
  private api: AxiosInstance;
  private authToken: string | null = null;

  constructor() {
    this.api = axios.create({
      baseURL: Config.API_URL,
      timeout: Config.API_TIMEOUT,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Request interceptor - add auth token
    this.api.interceptors.request.use(
      async (config) => {
        // Get token from storage if not cached
        if (!this.authToken) {
          this.authToken = await AsyncStorage.getItem(Config.STORAGE_KEYS.AUTH_TOKEN);
        }

        // Add token to headers
        if (this.authToken) {
          config.headers.Authorization = `Bearer ${this.authToken}`;
        }

        // Add API key for Laravel routes
        config.headers['X-API-Key'] = process.env.ACS_API_KEY || 'acs-secret-key-change-in-production';

        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor - handle errors globally
    this.api.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Unauthorized - clear token and redirect to login
          await this.clearAuth();
          // Emit event for auth context to handle
          // In a real app, you'd use a global event emitter or state management
        }

        return Promise.reject(this.handleError(error));
      }
    );
  }

  /**
   * Handle API errors
   */
  private handleError(error: AxiosError): ApiError {
    if (error.response) {
      // Server responded with error
      return {
        message: (error.response.data as any)?.message || 'An error occurred',
        errors: (error.response.data as any)?.errors,
        status: error.response.status,
      };
    } else if (error.request) {
      // Request made but no response
      return {
        message: 'Network error. Please check your internet connection.',
        status: 0,
      };
    } else {
      // Something else happened
      return {
        message: error.message || 'An unexpected error occurred',
      };
    }
  }

  /**
   * Set authentication token
   */
  async setAuthToken(token: string): Promise<void> {
    this.authToken = token;
    await AsyncStorage.setItem(Config.STORAGE_KEYS.AUTH_TOKEN, token);
  }

  /**
   * Clear authentication
   */
  async clearAuth(): Promise<void> {
    this.authToken = null;
    await AsyncStorage.removeItem(Config.STORAGE_KEYS.AUTH_TOKEN);
    await AsyncStorage.removeItem(Config.STORAGE_KEYS.USER_DATA);
  }

  /**
   * Generic GET request
   */
  async get<T>(url: string, config?: AxiosRequestConfig) {
    const response = await this.api.get<T>(url, config);
    return response.data;
  }

  /**
   * Generic POST request
   */
  async post<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    const response = await this.api.post<T>(url, data, config);
    return response.data;
  }

  /**
   * Generic PUT request
   */
  async put<T>(url: string, data?: any, config?: AxiosRequestConfig) {
    const response = await this.api.put<T>(url, data, config);
    return response.data;
  }

  /**
   * Generic DELETE request
   */
  async delete<T>(url: string, config?: AxiosRequestConfig) {
    const response = await this.api.delete<T>(url, config);
    return response.data;
  }
}

// Export singleton instance
export const apiService = new ApiService();
export default apiService;
