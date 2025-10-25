/**
 * K6 Load Testing - TR-369 USP Protocol Scenario (FUNCTIONAL VALIDATION MODE)
 * 
 * This is the production-ready version with strict functional thresholds enabled.
 * All endpoints MUST be fully implemented and return 200 responses.
 * 
 * Use this for:
 * - Pre-production validation
 * - Release candidate testing
 * - Production readiness verification
 * 
 * For infrastructure hardening (404s acceptable), use tr369.js instead.
 */

import http from 'k6/http';
import ws from 'k6/ws';
import { check, sleep, group } from 'k6';
import { config } from '../utils/config.js';
import {
    generateSerialNumber,
    generateUspGetRequest,
    generateUspSetRequest,
    generateUspGetInstancesRequest,
    generateUspGetSupportedDmRequest,
    generateUspGetSupportedProtocolRequest,
    generateParameterBatch
} from '../utils/generators.js';
import { recordUspOperation, tr369Metrics } from '../utils/metrics.js';

// PRODUCTION-READY configuration with strict functional thresholds
export const options = {
    stages: [
        { duration: '3m', target: 1000 },      // Ramp-up to 1K devices
        { duration: '5m', target: 1000 },      // Sustain 1K devices
        { duration: '3m', target: 5000 },      // Ramp-up to 5K devices
        { duration: '5m', target: 5000 },      // Sustain 5K devices
        { duration: '5m', target: 10000 },     // Ramp-up to 10K devices
        { duration: '10m', target: 10000 },    // Sustain 10K devices
        { duration: '5m', target: 30000 },     // Ramp-up to 30K devices (peak USP load)
        { duration: '10m', target: 30000 },    // Sustain peak load
        { duration: '3m', target: 0 },         // Ramp-down
    ],
    
    thresholds: {
        // PERFORMANCE THRESHOLDS (always required)
        'http_req_duration{transport:usp_http}': ['p(95)<400', 'p(99)<800'],
        'http_req_duration{transport:usp_mqtt}': ['p(95)<200', 'p(99)<400'],
        'ws_session_duration': ['p(95)<300', 'p(99)<600'],
        
        // FUNCTIONAL THRESHOLDS (strict for production readiness)
        'http_req_failed': ['rate<0.02'],                          // <2% error rate
        'tr369_usp_operation_success_rate': ['rate>0.95'],         // >95% success rate
        
        // OPERATION-SPECIFIC THRESHOLDS
        'http_req_duration{operation:get}': ['p(95)<350'],
        'http_req_duration{operation:set}': ['p(95)<400'],
        'http_req_duration{operation:get_instances}': ['p(95)<450'],
        'http_req_duration{operation:get_supported_dm}': ['p(95)<500'],
        'http_req_duration{operation:get_supported_protocol}': ['p(95)<300'],
    },
};

/**
 * Setup - verify ALL TR-369 USP endpoints are functional
 */
export function setup() {
    console.log('🚀 Starting TR-369 USP FUNCTIONAL VALIDATION test');
    console.log(`Mode: PRODUCTION READINESS - All endpoints must return 200`);
    console.log(`USP HTTP Endpoint: ${config.baseUrl}/tr369/usp`);
    console.log(`USP MQTT Bridge: ${config.baseUrl}/tr369/mqtt/publish`);
    console.log(`USP WebSocket: ${config.protocols.tr369.wsEndpoint}`);
    console.log(`Target: 30,000 concurrent USP sessions`);
    
    // Test USP HTTP transport - MUST return 200
    const testDevice = 1;
    const testPaths = ['Device.DeviceInfo.SoftwareVersion'];
    const uspRequest = generateUspGetRequest(testDevice, testPaths);
    
    const httpResponse = http.post(
        `${config.baseUrl}/tr369/usp`,
        JSON.stringify(uspRequest),
        {
            headers: {
                'Content-Type': 'application/json',
                'X-USP-Protocol': 'HTTP',
            },
            timeout: '30s',
        }
    );
    
    if (httpResponse.status !== 200) {
        console.error(`❌ CRITICAL: USP HTTP endpoint returned ${httpResponse.status}`);
        console.error('Functional validation requires 200 responses.');
        console.error('Fix endpoint implementation or use tr369.js for infrastructure testing.');
        throw new Error(`USP HTTP endpoint not ready: ${httpResponse.status}`);
    }
    
    console.log('✅ USP HTTP endpoint is functional (200 OK)');
    
    // Test MQTT bridge - MUST return 200 or 201
    const mqttResponse = http.post(
        `${config.baseUrl}/tr369/mqtt/publish`,
        JSON.stringify({
            topic: `usp/agent/test`,
            payload: uspRequest,
            qos: 1,
        }),
        {
            headers: { 'Content-Type': 'application/json' },
            timeout: '30s',
        }
    );
    
    if (![200, 201].includes(mqttResponse.status)) {
        console.error(`❌ CRITICAL: MQTT bridge returned ${mqttResponse.status}`);
        throw new Error(`MQTT bridge not ready: ${mqttResponse.status}`);
    }
    
    console.log('✅ MQTT bridge is functional');
    console.log('✅ All prerequisites met - starting functional validation');
    
    return { 
        startTime: Date.now(),
        httpAvailable: true,
        mqttAvailable: true,
    };
}

/**
 * Main TR-369 USP test scenario
 * Distributes load across HTTP (40%), MQTT (30%), WebSocket (30%)
 */
export default function (data) {
    const deviceIndex = __VU * 1000 + __ITER;
    const deviceSerial = generateSerialNumber(deviceIndex);
    
    // Select transport based on distribution
    const transportRand = Math.random();
    
    if (transportRand < 0.4) {
        // 40% - HTTP transport (bulk operations)
        executeUspHttpTransport(deviceIndex, deviceSerial);
    } else if (transportRand < 0.7) {
        // 30% - MQTT transport (pub/sub messaging)
        executeUspMqttTransport(deviceIndex, deviceSerial);
    } else {
        // 30% - WebSocket transport (persistent connections)
        executeUspWebSocketTransport(deviceIndex, deviceSerial);
    }
    
    // Random sleep to simulate realistic device behavior
    sleep(Math.random() * 10 + 5);  // 5-15 seconds
}

/**
 * Execute USP over HTTP transport with functional validation
 */
function executeUspHttpTransport(deviceId, deviceSerial) {
    group('TR-369 USP HTTP Transport', function () {
        const rand = Math.random();
        
        let uspRequest;
        let operation;
        
        if (rand < 0.4) {
            const paths = generateParameterBatch(Math.floor(Math.random() * 5) + 1);
            uspRequest = generateUspGetRequest(deviceId, paths);
            operation = 'get';
            tr369Metrics.uspGetRequests.add(1);
        } else if (rand < 0.6) {
            const parameters = {
                ProvisioningCode: `PROV-${deviceSerial}`,
                PeriodicInformInterval: 300,
            };
            uspRequest = generateUspSetRequest(deviceId, parameters);
            operation = 'set';
            tr369Metrics.uspSetRequests.add(1);
        } else if (rand < 0.75) {
            uspRequest = generateUspGetInstancesRequest(deviceId);
            operation = 'get_instances';
        } else if (rand < 0.9) {
            uspRequest = generateUspGetSupportedDmRequest(deviceId);
            operation = 'get_supported_dm';
        } else {
            uspRequest = generateUspGetSupportedProtocolRequest(deviceId);
            operation = 'get_supported_protocol';
        }
        
        const startTime = Date.now();
        
        const response = http.post(
            `${config.baseUrl}/tr369/usp`,
            JSON.stringify(uspRequest),
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-USP-Protocol': 'HTTP',
                    'X-USP-Operation': operation,
                },
                tags: { 
                    transport: 'usp_http', 
                    protocol: 'tr369',
                    operation: operation,
                },
                timeout: '30s',
            }
        );
        
        const duration = Date.now() - startTime;
        const success = response.status === 200;
        
        // STRICT validation - only 200 is success
        check(response, {
            'usp_http status is 200': (r) => r.status === 200,
            'usp_http response time < 400ms': (r) => r.timings.duration < 400,
            'usp_http has valid response body': (r) => r.body && r.body.length > 0,
        });
        
        recordUspOperation('http', duration, success);
        tr369Metrics.httpTransportMessages.add(1);
    });
}

/**
 * Execute USP over MQTT transport with functional validation
 */
function executeUspMqttTransport(deviceId, deviceSerial) {
    group('TR-369 USP MQTT Transport', function () {
        const rand = Math.random();
        
        let uspRequest;
        let operation;
        
        if (rand < 0.4) {
            const paths = generateParameterBatch(2);
            uspRequest = generateUspGetRequest(deviceId, paths);
            operation = 'get';
        } else if (rand < 0.6) {
            const parameters = {
                ProvisioningCode: `PROV-${deviceSerial}`,
                PeriodicInformInterval: 300,
            };
            uspRequest = generateUspSetRequest(deviceId, parameters);
            operation = 'set';
        } else if (rand < 0.75) {
            uspRequest = generateUspGetInstancesRequest(deviceId);
            operation = 'get_instances';
        } else if (rand < 0.9) {
            uspRequest = generateUspGetSupportedDmRequest(deviceId);
            operation = 'get_supported_dm';
        } else {
            uspRequest = generateUspGetSupportedProtocolRequest(deviceId);
            operation = 'get_supported_protocol';
        }
        
        const startTime = Date.now();
        
        const response = http.post(
            `${config.baseUrl}/tr369/mqtt/publish`,
            JSON.stringify({
                topic: `usp/controller/${deviceId}`,
                payload: uspRequest,
                qos: 1,
            }),
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-USP-Protocol': 'MQTT',
                    'X-USP-Operation': operation,
                },
                tags: { 
                    transport: 'usp_mqtt', 
                    protocol: 'tr369',
                    operation: operation,
                },
                timeout: '30s',
            }
        );
        
        const duration = Date.now() - startTime;
        const success = [200, 201].includes(response.status);
        
        // STRICT validation - only 200/201 are success
        check(response, {
            'usp_mqtt publish is 200/201': (r) => [200, 201].includes(r.status),
            'usp_mqtt latency < 200ms': (r) => r.timings.duration < 200,
            'usp_mqtt has response data': (r) => r.body && r.body.length > 0,
        });
        
        recordUspOperation('mqtt', duration, success);
        tr369Metrics.mqttTransportMessages.add(1);
        tr369Metrics.mqttLatency.add(duration);
    });
}

/**
 * Execute USP over WebSocket transport
 */
function executeUspWebSocketTransport(deviceId, deviceSerial) {
    group('TR-369 USP WebSocket Transport', function () {
        const wsUrl = config.protocols.tr369.wsEndpoint || 'ws://localhost:8080/usp';
        const paths = generateParameterBatch(1);
        const uspRequest = generateUspGetRequest(deviceId, paths);
        
        const startTime = Date.now();
        let duration = 0;
        let messageReceived = false;
        
        const res = ws.connect(wsUrl, {
            headers: {
                'X-USP-Protocol': 'WebSocket',
                'X-Device-ID': deviceId.toString(),
            },
            tags: { 
                transport: 'usp_websocket', 
                protocol: 'tr369',
            },
        }, function (socket) {
            socket.on('open', function () {
                socket.send(JSON.stringify(uspRequest));
            });
            
            socket.on('message', function (data) {
                messageReceived = true;
                duration = Date.now() - startTime;
                socket.close();
            });
            
            socket.on('error', function (e) {
                console.log(`WebSocket error: ${e}`);
            });
            
            socket.on('close', function () {
                if (!messageReceived) {
                    duration = Date.now() - startTime;
                }
            });
            
            socket.setTimeout(function () {
                if (!messageReceived) {
                    socket.close();
                }
            }, 5000);
        });
        
        if (!messageReceived && duration === 0) {
            duration = Date.now() - startTime;
        }
        
        check(res, {
            'websocket connection established': (r) => r && r.status === 101,
            'websocket message received': () => messageReceived,
        });
        
        recordUspOperation('websocket', duration, messageReceived);
        tr369Metrics.wsTransportMessages.add(1);
        tr369Metrics.wsLatency.add(duration);
    });
}

/**
 * Teardown - print summary
 */
export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    const minutes = Math.floor(duration / 60);
    
    console.log(`\n✅ TR-369 USP FUNCTIONAL VALIDATION completed`);
    console.log(`Duration: ${minutes} minutes`);
    console.log(`Peak concurrent USP sessions: 30,000`);
    console.log(`Transport mix: 40% HTTP, 30% MQTT, 30% WebSocket`);
    console.log(`Mode: PRODUCTION READINESS - All thresholds enforced`);
}

/**
 * Custom summary handler
 */
export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test_type: 'TR-369 USP Protocol - Functional Validation',
        mode: 'production_readiness',
        duration_seconds: data.state.testRunDurationMs / 1000,
        peak_sessions: 30000,
        transport_distribution: {
            http: '40%',
            mqtt: '30%',
            websocket: '30%',
        },
        metrics: {
            http_reqs: data.metrics.http_reqs?.values,
            http_req_duration: data.metrics.http_req_duration?.values,
            http_req_failed: data.metrics.http_req_failed?.values,
            usp_records: data.metrics.tr369_usp_records_total?.values,
            usp_get_requests: data.metrics.tr369_usp_get_requests_total?.values,
            usp_set_requests: data.metrics.tr369_usp_set_requests_total?.values,
            http_transport: data.metrics.tr369_http_transport_messages_total?.values,
            mqtt_transport: data.metrics.tr369_mqtt_transport_messages_total?.values,
            ws_transport: data.metrics.tr369_websocket_transport_messages_total?.values,
            success_rate: data.metrics.tr369_usp_operation_success_rate?.values,
        },
        thresholds_passed: {
            error_rate: data.metrics.http_req_failed?.values.rate < 0.02,
            success_rate: data.metrics.tr369_usp_operation_success_rate?.values.rate > 0.95,
            http_p95: data.metrics['http_req_duration{transport:usp_http}']?.values['p(95)'] < 400,
            mqtt_p95: data.metrics['http_req_duration{transport:usp_mqtt}']?.values['p(95)'] < 200,
        },
    };
    
    return {
        'reports/tr369-functional-summary.json': JSON.stringify(summary, null, 2),
        'stdout': textSummary(data, summary),
    };
}

function textSummary(data, summary) {
    const metrics = data.metrics;
    let output = '\n';
    
    output += '='.repeat(80) + '\n';
    output += '  TR-369 USP Protocol - FUNCTIONAL VALIDATION Results\n';
    output += '='.repeat(80) + '\n\n';
    
    output += `  Mode: PRODUCTION READINESS\n`;
    output += `  Status: ${summary.thresholds_passed.error_rate && summary.thresholds_passed.success_rate ? '✅ PASSED' : '❌ FAILED'}\n\n`;
    
    if (metrics.http_reqs) {
        output += `  Total USP Requests: ${metrics.http_reqs.values.count}\n`;
        output += `  Request Rate: ${metrics.http_reqs.values.rate.toFixed(2)}/s\n\n`;
    }
    
    if (metrics.http_req_failed) {
        const errorRate = (metrics.http_req_failed.values.rate * 100).toFixed(2);
        const passed = metrics.http_req_failed.values.rate < 0.02 ? '✅' : '❌';
        output += `  ${passed} Error Rate: ${errorRate}% (threshold: <2%)\n`;
    }
    
    if (metrics.tr369_usp_operation_success_rate) {
        const successRate = (metrics.tr369_usp_operation_success_rate.values.rate * 100).toFixed(2);
        const passed = metrics.tr369_usp_operation_success_rate.values.rate > 0.95 ? '✅' : '❌';
        output += `  ${passed} USP Success Rate: ${successRate}% (threshold: >95%)\n\n`;
    }
    
    output += `  Transport Distribution:\n`;
    if (metrics.tr369_http_transport_messages_total) {
        output += `    HTTP:      ${metrics.tr369_http_transport_messages_total.values.count} messages\n`;
    }
    if (metrics.tr369_mqtt_transport_messages_total) {
        output += `    MQTT:      ${metrics.tr369_mqtt_transport_messages_total.values.count} messages\n`;
    }
    if (metrics.tr369_websocket_transport_messages_total) {
        output += `    WebSocket: ${metrics.tr369_websocket_transport_messages_total.values.count} messages\n`;
    }
    
    if (metrics.http_req_duration) {
        output += `\n  Response Times:\n`;
        output += `    avg: ${metrics.http_req_duration.values.avg.toFixed(2)}ms\n`;
        output += `    p(95): ${metrics.http_req_duration.values['p(95)'].toFixed(2)}ms\n`;
        output += `    p(99): ${metrics.http_req_duration.values['p(99)'].toFixed(2)}ms\n`;
    }
    
    output += '\n' + '='.repeat(80) + '\n';
    
    return output;
}
