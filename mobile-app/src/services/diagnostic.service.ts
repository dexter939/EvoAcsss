import apiService from './api';
import Config from '../constants/config';
import { DiagnosticTest, ApiResponse } from '../types';

/**
 * Diagnostic Service
 * 
 * Handles TR-143 diagnostic test API calls
 */

class DiagnosticService {
  /**
   * Get all diagnostic tests
   */
  async getDiagnostics(params?: {
    page?: number;
    perPage?: number;
    device_id?: number;
  }): Promise<ApiResponse<DiagnosticTest[]>> {
    return await apiService.get<ApiResponse<DiagnosticTest[]>>(
      Config.ENDPOINTS.DIAGNOSTICS,
      { params }
    );
  }

  /**
   * Get diagnostic test by ID
   */
  async getDiagnostic(id: number): Promise<DiagnosticTest> {
    return await apiService.get<DiagnosticTest>(
      `${Config.ENDPOINTS.DIAGNOSTICS}/${id}`
    );
  }

  /**
   * Run ping test
   */
  async runPingTest(deviceId: number, target: string): Promise<DiagnosticTest> {
    return await apiService.post<DiagnosticTest>(
      Config.ENDPOINTS.DIAGNOSTICS,
      {
        device_id: deviceId,
        test_type: 'ping',
        target,
      }
    );
  }

  /**
   * Run traceroute test
   */
  async runTracerouteTest(deviceId: number, target: string): Promise<DiagnosticTest> {
    return await apiService.post<DiagnosticTest>(
      Config.ENDPOINTS.DIAGNOSTICS,
      {
        device_id: deviceId,
        test_type: 'traceroute',
        target,
      }
    );
  }

  /**
   * Run download speed test
   */
  async runDownloadTest(deviceId: number, url: string): Promise<DiagnosticTest> {
    return await apiService.post<DiagnosticTest>(
      Config.ENDPOINTS.DIAGNOSTICS,
      {
        device_id: deviceId,
        test_type: 'download',
        target: url,
      }
    );
  }

  /**
   * Run upload speed test
   */
  async runUploadTest(deviceId: number, url: string): Promise<DiagnosticTest> {
    return await apiService.post<DiagnosticTest>(
      Config.ENDPOINTS.DIAGNOSTICS,
      {
        device_id: deviceId,
        test_type: 'upload',
        target: url,
      }
    );
  }

  /**
   * Get test status
   */
  async getTestStatus(id: number): Promise<DiagnosticTest> {
    return await this.getDiagnostic(id);
  }
}

export const diagnosticService = new DiagnosticService();
export default diagnosticService;
