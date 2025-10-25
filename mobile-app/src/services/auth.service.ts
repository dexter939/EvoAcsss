import AsyncStorage from '@react-native-async-storage/async-storage';
import apiService from './api';
import Config from '../constants/config';
import { AuthResponse, LoginCredentials, User } from '../types';

/**
 * Authentication Service
 * 
 * Handles user authentication, token management, and user data
 */

class AuthService {
  /**
   * Login user
   */
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await apiService.post<AuthResponse>(
      Config.ENDPOINTS.LOGIN,
      credentials
    );

    // Store token
    await apiService.setAuthToken(response.token);

    // Store user data
    await AsyncStorage.setItem(
      Config.STORAGE_KEYS.USER_DATA,
      JSON.stringify(response.user)
    );

    return response;
  }

  /**
   * Logout user
   */
  async logout(): Promise<void> {
    try {
      await apiService.post(Config.ENDPOINTS.LOGOUT);
    } catch (error) {
      // Ignore errors on logout
      console.error('Logout error:', error);
    } finally {
      // Clear local storage
      await apiService.clearAuth();
    }
  }

  /**
   * Get current user from storage
   */
  async getCurrentUser(): Promise<User | null> {
    const userData = await AsyncStorage.getItem(Config.STORAGE_KEYS.USER_DATA);
    if (userData) {
      return JSON.parse(userData);
    }
    return null;
  }

  /**
   * Check if user is authenticated
   */
  async isAuthenticated(): Promise<boolean> {
    const token = await AsyncStorage.getItem(Config.STORAGE_KEYS.AUTH_TOKEN);
    return !!token;
  }

  /**
   * Get auth token
   */
  async getAuthToken(): Promise<string | null> {
    return await AsyncStorage.getItem(Config.STORAGE_KEYS.AUTH_TOKEN);
  }
}

export const authService = new AuthService();
export default authService;
