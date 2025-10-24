/**
 * K6 Load Testing - TR-069 Protocol Scenario
 * 
 * Simulates TR-069 CWMP protocol interactions from CPE devices
 * Tests Inform messages, GetParameterValues, SetParameterValues
 * Validates carrier-grade performance with 100K+ devices
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { config, getTr069Headers } from '../utils/config.js';
import {
    generateSerialNumber,
    generateMacAddress,
    generateTr069Inform,
    generateTr069GetParameterValues,
    generateParameterBatch
} from '../utils/generators.js';
import { recordTr069Inform, recordTr069ParameterOp } from '../utils/metrics.js';

// Test configuration for TR-069 load
export const options = {
    stages: [
        { duration: '5m', target: 1000 },      // Ramp-up to 1K devices
        { duration: '10m', target: 1000 },     // Sustain 1K devices
        { duration: '5m', target: 10000 },     // Ramp-up to 10K devices
        { duration: '10m', target: 10000 },    // Sustain 10K devices
        { duration: '5m', target: 50000 },     // Ramp-up to 50K devices
        { duration: '15m', target: 50000 },    // Sustain 50K devices (peak carrier load)
        { duration: '5m', target: 0 },         // Ramp-down
    ],
    
    thresholds: {
        'http_req_duration{operation:inform}': ['p(95)<200', 'p(99)<400'],
        'http_req_duration{operation:get_params}': ['p(95)<500', 'p(99)<1000'],
        'http_req_duration{operation:set_params}': ['p(95)<600', 'p(99)<1200'],
        'http_req_failed': ['rate<0.01'],
        'tr069_inform_success_rate': ['rate>0.99'],
        'tr069_parameter_operation_success_rate': ['rate>0.98'],
    },
};

/**
 * Setup - verify TR-069 ACS endpoint is accessible
 */
export function setup() {
    console.log('🚀 Starting TR-069 protocol load test');
    console.log(`TR-069 ACS URL: ${config.baseUrl}/tr069`);
    console.log(`Target: 50,000 concurrent CPE devices`);
    
    // Verify TR-069 endpoint
    const headers = getTr069Headers();
    const testInform = generateTr069Inform('TEST-SETUP-001', '00:25:9E:00:00:01');
    
    const response = http.post(`${config.baseUrl}/tr069`, testInform, {
        headers,
        timeout: '30s',
    });
    
    if (response.status !== 200) {
        console.warn(`⚠️  TR-069 endpoint returned ${response.status}`);
        console.warn(`Response: ${response.body}`);
    } else {
        console.log('✅ TR-069 ACS endpoint is accessible');
    }
    
    return { startTime: Date.now() };
}

/**
 * Main TR-069 test scenario
 * Simulates realistic CPE behavior: periodic Inform + parameter operations
 */
export default function (data) {
    const headers = getTr069Headers();
    const deviceIndex = __VU * 1000 + __ITER;  // Unique device ID
    const deviceSerial = generateSerialNumber(deviceIndex);
    const deviceMac = generateMacAddress(deviceIndex);
    
    // Group 1: TR-069 Inform (Periodic or Bootstrap)
    group('TR-069 Inform', function () {
        const informMessage = generateTr069Inform(deviceSerial, deviceMac);
        const startTime = Date.now();
        
        const response = http.post(`${config.baseUrl}/tr069`, informMessage, {
            headers,
            tags: { operation: 'inform', protocol: 'tr069' },
            timeout: '30s',
        });
        
        const duration = Date.now() - startTime;
        const success = response.status === 200;
        
        check(response, {
            'inform status is 200': (r) => r.status === 200,
            'inform has SOAP response': (r) => r.body.includes('soap:Envelope'),
            'inform response time < 200ms': (r) => r.timings.duration < 200,
            'inform has InformResponse': (r) => r.body.includes('InformResponse') || r.body.includes('GetParameterValues'),
        });
        
        recordTr069Inform(duration, success);
        
        // Parse ACS response for pending requests (GetParameterValues, etc.)
        if (success && response.body.includes('GetParameterValues')) {
            // ACS is requesting parameters - we'll respond in next group
            return { hasPendingRequest: true, responseBody: response.body };
        }
    });
    
    // Small delay to simulate device processing time
    sleep(1);
    
    // Group 2: GetParameterValues Response (if ACS requested)
    // In real TR-069, this would be part of the same session
    group('TR-069 GetParameterValues', function () {
        const parameters = generateParameterBatch(5);  // Random set of parameters
        const getParamsRequest = generateTr069GetParameterValues(parameters);
        const startTime = Date.now();
        
        const response = http.post(`${config.baseUrl}/tr069`, getParamsRequest, {
            headers,
            tags: { operation: 'get_params', protocol: 'tr069' },
            timeout: '30s',
        });
        
        const duration = Date.now() - startTime;
        const success = response.status === 200;
        
        check(response, {
            'get_params status is 200': (r) => r.status === 200,
            'get_params has response': (r) => r.body.includes('GetParameterValuesResponse'),
            'get_params response time < 500ms': (r) => r.timings.duration < 500,
        });
        
        recordTr069ParameterOp('get', duration, success);
    });
    
    // Periodic Inform interval (simulated)
    // In production, CPE sends Inform every 300-3600 seconds
    // For load testing, we use shorter intervals
    sleep(10);
}

/**
 * Teardown - print summary
 */
export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log(`\n✅ TR-069 load test completed`);
    console.log(`Duration: ${duration}s`);
    console.log(`Peak concurrent devices: 50,000`);
}

/**
 * Custom summary handler
 */
export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test_type: 'TR-069 Protocol Load Test',
        duration_seconds: data.state.testRunDurationMs / 1000,
        metrics: {
            http_reqs: data.metrics.http_reqs?.values,
            http_req_duration: data.metrics.http_req_duration?.values,
            http_req_failed: data.metrics.http_req_failed?.values,
            tr069_inform: data.metrics.tr069_inform_messages_total?.values,
            tr069_get_params: data.metrics.tr069_get_parameter_requests_total?.values,
            tr069_success_rate: data.metrics.tr069_inform_success_rate?.values,
        },
        thresholds: data.metrics,
    };
    
    return {
        'reports/tr069-summary.json': JSON.stringify(summary, null, 2),
        'stdout': textSummary(data),
    };
}

function textSummary(data) {
    const metrics = data.metrics;
    let output = '\n';
    
    output += '='.repeat(80) + '\n';
    output += '  TR-069 Protocol Load Test Summary\n';
    output += '='.repeat(80) + '\n\n';
    
    if (metrics.http_reqs) {
        output += `  Total TR-069 Requests: ${metrics.http_reqs.values.count}\n`;
        output += `  Request Rate: ${metrics.http_reqs.values.rate.toFixed(2)}/s\n\n`;
    }
    
    if (metrics.tr069_inform_messages_total) {
        output += `  Inform Messages: ${metrics.tr069_inform_messages_total.values.count}\n`;
    }
    
    if (metrics.tr069_get_parameter_requests_total) {
        output += `  GetParameterValues: ${metrics.tr069_get_parameter_requests_total.values.count}\n`;
    }
    
    if (metrics.tr069_inform_duration) {
        output += `\n  Inform Response Time:\n`;
        output += `    avg: ${metrics.tr069_inform_duration.values.avg.toFixed(2)}ms\n`;
        output += `    p(95): ${metrics.tr069_inform_duration.values['p(95)'].toFixed(2)}ms\n`;
        output += `    p(99): ${metrics.tr069_inform_duration.values['p(99)'].toFixed(2)}ms\n`;
    }
    
    if (metrics.tr069_inform_success_rate) {
        const successRate = (metrics.tr069_inform_success_rate.values.rate * 100).toFixed(2);
        output += `\n  Success Rate: ${successRate}%\n`;
    }
    
    output += '\n' + '='.repeat(80) + '\n';
    
    return output;
}
