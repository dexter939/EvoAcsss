/**
 * K6 Load Testing - TR-369 USP CI/CD Validation Scenario
 * 
 * Lightweight version for GitHub Actions CI/CD pipeline.
 * Tests functional correctness with minimal resource usage.
 * 
 * Use this for:
 * - CI/CD pipeline validation
 * - Pre-commit smoke testing
 * - Quick functional verification
 * 
 * For full load testing, use tr369-functional.js locally.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { config } from '../utils/config.js';
import {
    generateUspGetRequest,
    generateUspSetRequest,
    generateUspGetInstancesRequest,
    generateUspGetSupportedDmRequest,
    generateUspGetSupportedProtocolRequest,
} from '../utils/generators.js';

// CI-FRIENDLY configuration: lightweight, fast, strict
export const options = {
    stages: [
        { duration: '30s', target: 5 },   // Ramp-up to 5 VUs
        { duration: '1m', target: 5 },    // Sustain 5 VUs
        { duration: '30s', target: 10 },  // Brief spike to 10 VUs
        { duration: '30s', target: 0 },   // Ramp-down
    ],
    
    thresholds: {
        // PERFORMANCE THRESHOLDS
        'http_req_duration': ['p(95)<2000', 'p(99)<3000'],
        
        // FUNCTIONAL THRESHOLDS (strict - must pass for CI to succeed)
        'http_req_failed': ['rate<0.02'],                          // <2% error rate
        'checks': ['rate>0.95'],                                    // >95% success rate
        
        // OPERATION-SPECIFIC THRESHOLDS
        'http_req_duration{operation:GET}': ['p(95)<1000'],
        'http_req_duration{operation:SET}': ['p(95)<1500'],
        'http_req_duration{operation:GET_INSTANCES}': ['p(95)<1500'],
        'http_req_duration{operation:GET_SUPPORTED_DM}': ['p(95)<2000'],
        'http_req_duration{operation:GET_SUPPORTED_PROTOCOL}': ['p(95)<1000'],
    },
};

/**
 * Setup - verify TR-369 USP endpoint is functional
 */
export function setup() {
    console.log('🚀 Starting TR-369 USP CI/CD VALIDATION');
    console.log(`Mode: CI/CD Pipeline - Lightweight functional testing`);
    console.log(`USP HTTP Endpoint: ${config.baseUrl}/usp`);
    console.log(`Duration: ~2.5 minutes`);
    console.log(`VUs: 5-10 (GitHub Actions friendly)`);
    
    // Pre-flight check: USP endpoint must be reachable
    const testDevice = 1;
    const testPaths = ['Device.DeviceInfo.SoftwareVersion'];
    const uspRequest = generateUspGetRequest(testDevice, testPaths);
    
    const response = http.post(
        `${config.baseUrl}/usp`,
        JSON.stringify(uspRequest),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': config.apiKey,
            },
            timeout: '10s',
        }
    );
    
    if (response.status !== 200) {
        console.error(`❌ CRITICAL: USP endpoint returned ${response.status}`);
        console.error('CI/CD validation requires functional endpoint.');
        throw new Error(`USP endpoint not ready: ${response.status}`);
    }
    
    console.log('✅ USP endpoint is functional (200 OK)');
    console.log('✅ Starting lightweight load test...');
    
    return { startTime: Date.now() };
}

/**
 * Main test scenario - test all USP message types
 */
export default function () {
    const deviceId = Math.floor(Math.random() * 100) + 1;
    
    // Random operation selection (weighted distribution)
    const rand = Math.random();
    let operation, uspRequest, response;
    
    if (rand < 0.40) {
        // 40% GET operations
        operation = 'GET';
        const paths = [
            'Device.DeviceInfo.SoftwareVersion',
            'Device.DeviceInfo.HardwareVersion',
            'Device.DeviceInfo.ModelName',
        ];
        uspRequest = generateUspGetRequest(deviceId, paths);
        
    } else if (rand < 0.60) {
        // 20% SET operations
        operation = 'SET';
        uspRequest = generateUspSetRequest(deviceId, {
            'Device.ManagementServer.PeriodicInformInterval': '300',
        });
        
    } else if (rand < 0.75) {
        // 15% GET_INSTANCES
        operation = 'GET_INSTANCES';
        uspRequest = generateUspGetInstancesRequest(deviceId, 'Device.WiFi.SSID.', false);
        
    } else if (rand < 0.90) {
        // 15% GET_SUPPORTED_DM
        operation = 'GET_SUPPORTED_DM';
        uspRequest = generateUspGetSupportedDmRequest(deviceId, ['Device.DeviceInfo.']);
        
    } else {
        // 10% GET_SUPPORTED_PROTOCOL
        operation = 'GET_SUPPORTED_PROTOCOL';
        uspRequest = generateUspGetSupportedProtocolRequest(deviceId);
    }
    
    // Execute USP request
    response = http.post(
        `${config.baseUrl}/usp`,
        JSON.stringify(uspRequest),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': config.apiKey,
            },
            tags: { operation: operation },
            timeout: '10s',
        }
    );
    
    // Validate response
    const success = check(response, {
        'status is 200': (r) => r.status === 200,
        'response has body': (r) => r.body && r.body.length > 0,
        'response time < 3s': (r) => r.timings.duration < 3000,
    });
    
    if (!success) {
        console.error(`❌ ${operation} failed: ${response.status} - ${response.body}`);
    }
    
    // Brief sleep to simulate realistic device behavior
    sleep(Math.random() * 2 + 1); // 1-3 seconds
}

/**
 * Teardown - print summary
 */
export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log('\n=== CI/CD Validation Complete ===');
    console.log(`Duration: ${duration.toFixed(1)}s`);
    console.log('If all thresholds passed, CI/CD pipeline will succeed ✅');
}
