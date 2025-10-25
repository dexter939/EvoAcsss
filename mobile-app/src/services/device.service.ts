import apiService from './api';
import Config from '../constants/config';
import { Device, DeviceStats, ApiResponse } from '../types';

/**
 * Device Service
 * 
 * Handles device-related API calls
 */

class DeviceService {
  /**
   * Get all devices with optional filters
   */
  async getDevices(params?: {
    page?: number;
    perPage?: number;
    search?: string;
    status?: string;
  }): Promise<ApiResponse<Device[]>> {
    return await apiService.get<ApiResponse<Device[]>>(
      Config.ENDPOINTS.DEVICES,
      { params }
    );
  }

  /**
   * Get device by ID
   */
  async getDevice(id: number): Promise<Device> {
    return await apiService.get<Device>(`${Config.ENDPOINTS.DEVICES}/${id}`);
  }

  /**
   * Get device statistics
   */
  async getDeviceStats(): Promise<DeviceStats> {
    return await apiService.get<DeviceStats>(`${Config.ENDPOINTS.DEVICES}/stats`);
  }

  /**
   * Search devices
   */
  async searchDevices(query: string): Promise<Device[]> {
    const response = await apiService.get<ApiResponse<Device[]>>(
      `${Config.ENDPOINTS.DEVICES}/search`,
      { params: { q: query } }
    );
    return response.data;
  }

  /**
   * Reboot device
   */
  async rebootDevice(id: number): Promise<void> {
    await apiService.post(`${Config.ENDPOINTS.DEVICES}/${id}/reboot`);
  }

  /**
   * Factory reset device
   */
  async factoryResetDevice(id: number): Promise<void> {
    await apiService.post(`${Config.ENDPOINTS.DEVICES}/${id}/factory-reset`);
  }
}

export const deviceService = new DeviceService();
export default deviceService;
