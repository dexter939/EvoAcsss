import apiService from './api';
import Config from '../constants/config';
import { Alarm, AlarmStats, ApiResponse } from '../types';

/**
 * Alarm Service
 * 
 * Handles alarm-related API calls
 */

class AlarmService {
  /**
   * Get all alarms with optional filters
   */
  async getAlarms(params?: {
    page?: number;
    perPage?: number;
    severity?: string;
    acknowledged?: boolean;
  }): Promise<ApiResponse<Alarm[]>> {
    return await apiService.get<ApiResponse<Alarm[]>>(
      Config.ENDPOINTS.ALARMS,
      { params }
    );
  }

  /**
   * Get alarm by ID
   */
  async getAlarm(id: number): Promise<Alarm> {
    return await apiService.get<Alarm>(`${Config.ENDPOINTS.ALARMS}/${id}`);
  }

  /**
   * Get alarm statistics
   */
  async getAlarmStats(): Promise<AlarmStats> {
    return await apiService.get<AlarmStats>(`${Config.ENDPOINTS.ALARMS}/stats`);
  }

  /**
   * Acknowledge alarm
   */
  async acknowledgeAlarm(id: number): Promise<void> {
    await apiService.post(`${Config.ENDPOINTS.ALARMS}/${id}/acknowledge`);
  }

  /**
   * Resolve alarm
   */
  async resolveAlarm(id: number): Promise<void> {
    await apiService.post(`${Config.ENDPOINTS.ALARMS}/${id}/resolve`);
  }

  /**
   * Get unacknowledged alarms count
   */
  async getUnacknowledgedCount(): Promise<number> {
    const stats = await this.getAlarmStats();
    return stats.unacknowledged;
  }
}

export const alarmService = new AlarmService();
export default alarmService;
