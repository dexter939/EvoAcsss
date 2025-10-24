/**
 * K6 Load Testing - REST API Scenario
 * 
 * Tests ACS REST API endpoints with realistic user behavior patterns
 * Simulates device management operations: list, search, create, update, delete
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { config, getApiHeaders, getThresholds, randomSleep } from '../utils/config.js';
import { 
    generateDeviceData, 
    generateSearchQuery,
    generateSerialNumber 
} from '../utils/generators.js';
import { recordApiRequest, apiMetrics } from '../utils/metrics.js';

// Test configuration
export const options = {
    stages: [
        { duration: '2m', target: 100 },      // Ramp-up to 100 users
        { duration: '5m', target: 100 },      // Stay at 100 users
        { duration: '2m', target: 500 },      // Ramp-up to 500 users
        { duration: '5m', target: 500 },      // Stay at 500 users
        { duration: '2m', target: 1000 },     // Ramp-up to 1000 users
        { duration: '10m', target: 1000 },    // Stay at 1000 for sustained load
        { duration: '2m', target: 0 },        // Ramp-down to 0 users
    ],
    
    thresholds: {
        'http_req_duration{endpoint:list}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{endpoint:search}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{endpoint:create}': ['p(95)<800', 'p(99)<1500'],
        'http_req_duration{endpoint:update}': ['p(95)<600', 'p(99)<1200'],
        'http_req_failed': ['rate<0.01'],     // Less than 1% errors
        'api_request_success_rate': ['rate>0.99'],  // 99%+ success rate
    },
};

/**
 * Setup function - runs once per VU
 */
export function setup() {
    console.log('🚀 Starting REST API load test');
    console.log(`Base URL: ${config.apiUrl}`);
    console.log(`API Key: ${config.apiKey.substring(0, 10)}...`);
    
    // Verify API is accessible
    const headers = getApiHeaders();
    const response = http.get(`${config.apiUrl}/devices`, { headers });
    
    if (response.status !== 200) {
        throw new Error(`API not accessible: ${response.status}`);
    }
    
    console.log('✅ API is accessible');
    return { startTime: Date.now() };
}

/**
 * Main test scenario
 */
export default function (data) {
    const headers = getApiHeaders();
    
    // Group 1: Device Listing
    group('Device Listing', function () {
        const startTime = Date.now();
        
        // Test pagination
        const response = http.get(`${config.apiUrl}/devices?page=1&per_page=50`, {
            headers,
            tags: { endpoint: 'list', type: 'api' },
        });
        
        const duration = Date.now() - startTime;
        
        check(response, {
            'list status is 200': (r) => r.status === 200,
            'list has data': (r) => r.json('data') !== undefined,
            'list has pagination': (r) => r.json('meta') !== undefined,
            'list response < 500ms': (r) => r.timings.duration < 500,
        });
        
        recordApiRequest('list', duration, response.status);
        apiMetrics.deviceListRequests.add(1);
    });
    
    sleep(randomSleep(500, 2000));
    
    // Group 2: Device Search
    group('Device Search', function () {
        const startTime = Date.now();
        const searchQuery = generateSearchQuery();
        
        const response = http.get(
            `${config.apiUrl}/devices?search=${encodeURIComponent(searchQuery)}`,
            {
                headers,
                tags: { endpoint: 'search', type: 'api' },
            }
        );
        
        const duration = Date.now() - startTime;
        
        check(response, {
            'search status is 200': (r) => r.status === 200,
            'search has data': (r) => r.json('data') !== undefined,
            'search response < 500ms': (r) => r.timings.duration < 500,
        });
        
        recordApiRequest('search', duration, response.status);
        apiMetrics.deviceSearchRequests.add(1);
    });
    
    sleep(randomSleep(1000, 3000));
    
    // Group 3: Device Filtering
    group('Device Filtering', function () {
        const startTime = Date.now();
        
        const filters = [
            `manufacturer=Huawei`,
            `device_type=ONT`,
            `status=online`,
        ].join('&');
        
        const response = http.get(`${config.apiUrl}/devices?${filters}`, {
            headers,
            tags: { endpoint: 'filter', type: 'api' },
        });
        
        const duration = Date.now() - startTime;
        
        check(response, {
            'filter status is 200': (r) => r.status === 200,
            'filter has data': (r) => r.json('data') !== undefined,
        });
        
        recordApiRequest('filter', duration, response.status);
    });
    
    sleep(randomSleep(1000, 2000));
    
    // Group 4: Device Details
    group('Device Details', function () {
        // First get list to find a device ID
        const listResponse = http.get(`${config.apiUrl}/devices?per_page=1`, { headers });
        
        if (listResponse.status === 200) {
            const devices = listResponse.json('data');
            if (devices && devices.length > 0) {
                const deviceId = devices[0].id;
                const startTime = Date.now();
                
                const response = http.get(`${config.apiUrl}/devices/${deviceId}`, {
                    headers,
                    tags: { endpoint: 'details', type: 'api' },
                });
                
                const duration = Date.now() - startTime;
                
                check(response, {
                    'details status is 200': (r) => r.status === 200,
                    'details has device data': (r) => r.json('data') !== undefined,
                    'details response < 300ms': (r) => r.timings.duration < 300,
                });
                
                recordApiRequest('details', duration, response.status);
            }
        }
    });
    
    sleep(randomSleep(2000, 5000));
    
    // Group 5: Device Creation (20% of users)
    if (Math.random() < 0.2) {
        group('Device Creation', function () {
            const deviceData = generateDeviceData(__VU * 100000 + __ITER);
            const startTime = Date.now();
            
            const response = http.post(
                `${config.apiUrl}/devices`,
                JSON.stringify(deviceData),
                {
                    headers,
                    tags: { endpoint: 'create', type: 'api' },
                }
            );
            
            const duration = Date.now() - startTime;
            
            check(response, {
                'create status is 201 or 422': (r) => [201, 422].includes(r.status),
                'create response < 800ms': (r) => r.timings.duration < 800,
            });
            
            recordApiRequest('create', duration, response.status);
            apiMetrics.deviceCreateRequests.add(1);
            
            // If successful, store device ID for later
            if (response.status === 201) {
                const createdDevice = response.json('data');
                return createdDevice.id;
            }
        });
    }
    
    sleep(randomSleep(1000, 3000));
}

/**
 * Teardown function - runs once after all VUs finish
 */
export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log(`\n✅ REST API load test completed`);
    console.log(`Duration: ${duration}s`);
}

/**
 * Handle summary for custom reporting
 */
export function handleSummary(data) {
    return {
        'reports/api-rest-summary.json': JSON.stringify(data, null, 2),
        'stdout': textSummary(data, { indent: '  ', enableColors: true }),
    };
}

/**
 * Simple text summary (K6 doesn't export textSummary by default, so we implement basic version)
 */
function textSummary(data, options) {
    const metrics = data.metrics;
    let summary = '\n';
    
    summary += '='.repeat(80) + '\n';
    summary += '  REST API Load Test Summary\n';
    summary += '='.repeat(80) + '\n\n';
    
    // HTTP metrics
    if (metrics.http_reqs) {
        summary += `  HTTP Requests: ${metrics.http_reqs.values.count}\n`;
        summary += `  Request Rate: ${metrics.http_reqs.values.rate.toFixed(2)}/s\n\n`;
    }
    
    if (metrics.http_req_duration) {
        summary += `  Response Time:\n`;
        summary += `    avg: ${metrics.http_req_duration.values.avg.toFixed(2)}ms\n`;
        summary += `    min: ${metrics.http_req_duration.values.min.toFixed(2)}ms\n`;
        summary += `    med: ${metrics.http_req_duration.values.med.toFixed(2)}ms\n`;
        summary += `    max: ${metrics.http_req_duration.values.max.toFixed(2)}ms\n`;
        summary += `    p(95): ${metrics.http_req_duration.values['p(95)'].toFixed(2)}ms\n`;
        summary += `    p(99): ${metrics.http_req_duration.values['p(99)'].toFixed(2)}ms\n\n`;
    }
    
    if (metrics.http_req_failed) {
        const failRate = (metrics.http_req_failed.values.rate * 100).toFixed(2);
        summary += `  Error Rate: ${failRate}%\n\n`;
    }
    
    // Custom API metrics
    if (metrics.api_device_list_requests_total) {
        summary += `  API Operations:\n`;
        summary += `    List Requests: ${metrics.api_device_list_requests_total.values.count}\n`;
    }
    if (metrics.api_device_search_requests_total) {
        summary += `    Search Requests: ${metrics.api_device_search_requests_total.values.count}\n`;
    }
    if (metrics.api_device_create_requests_total) {
        summary += `    Create Requests: ${metrics.api_device_create_requests_total.values.count}\n`;
    }
    
    summary += '\n' + '='.repeat(80) + '\n';
    
    return summary;
}
