/**
 * K6 Load Testing - Configuration Management
 * 
 * Centralized configuration for all load testing scenarios
 */

export const config = {
    // Base URL for ACS server
    baseUrl: __ENV.ACS_BASE_URL || 'http://localhost:5000',
    apiUrl: __ENV.ACS_API_URL || 'http://localhost:5000/api/v1',
    
    // Authentication
    apiKey: __ENV.ACS_API_KEY || 'test-api-key-12345',
    adminEmail: __ENV.ADMIN_EMAIL || 'admin@example.com',
    adminPassword: __ENV.ADMIN_PASSWORD || 'password123',
    
    // Test configuration
    thresholds: {
        // HTTP performance thresholds
        http_req_duration_p95: 500,    // 95th percentile < 500ms
        http_req_duration_p99: 1000,   // 99th percentile < 1000ms
        http_req_failed_rate: 0.01,    // Error rate < 1%
        
        // TR-069 specific
        tr069_inform_duration: 200,     // Inform processing < 200ms
        tr069_param_duration: 500,      // Parameter ops < 500ms
        
        // TR-369 USP specific
        usp_record_duration: 300,       // USP Record processing < 300ms
        mqtt_latency: 100,              // MQTT message < 100ms
    },
    
    // Virtual Users (VUs) configurations
    vus: {
        smoke: 10,          // Smoke test
        load: 1000,         // Normal load
        stress: 10000,      // Stress test
        spike: 50000,       // Spike test
        soak: 5000,         // Soak test (prolonged)
        peak: 100000,       // Peak load (100K devices)
    },
    
    // Duration configurations
    duration: {
        smoke: '1m',
        load: '5m',
        stress: '10m',
        spike: '2m',
        soak: '4h',
        peak: '30m',
    },
    
    // Ramp-up stages for progressive load
    stages: {
        warmup: { target: 1000, duration: '2m' },
        normal: { target: 10000, duration: '5m' },
        high: { target: 50000, duration: '5m' },
        peak: { target: 100000, duration: '10m' },
        sustain: { target: 100000, duration: '10m' },
        rampdown: { target: 0, duration: '5m' },
    },
    
    // Database configuration
    database: {
        maxConnections: 200,
        poolTimeout: 30000,
    },
    
    // Cache configuration
    cache: {
        redisDb: 1,
        ttl: 3600,
    },
    
    // Protocol-specific settings
    protocols: {
        tr069: {
            soapAction: 'urn:dslforum-org:cwmp-1-0',
            cwmpVersion: '1.0',
            deviceManufacturer: 'LoadTest',
            deviceOui: '00259E',
        },
        tr369: {
            controllerEndpoint: 'proto://controller.acs.local',
            deviceEndpoint: 'proto://device-{id}.cpe.local',
            mqttBroker: __ENV.MQTT_BROKER || 'mqtt://localhost:1883',
            wsEndpoint: __ENV.WS_ENDPOINT || 'ws://localhost:8080/usp',
        },
    },
};

/**
 * Get threshold configuration for K6
 */
export function getThresholds() {
    return {
        'http_req_duration{type:api}': [`p(95)<${config.thresholds.http_req_duration_p95}`],
        'http_req_duration{type:api}': [`p(99)<${config.thresholds.http_req_duration_p99}`],
        'http_req_failed': [`rate<${config.thresholds.http_req_failed_rate}`],
        'http_reqs': ['count>0'],
        'vus': ['value>0'],
    };
}

/**
 * Get headers for API authentication
 */
export function getApiHeaders() {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-API-Key': config.apiKey,
    };
}

/**
 * Get headers for TR-069 SOAP requests
 */
export function getTr069Headers() {
    return {
        'Content-Type': 'text/xml; charset=utf-8',
        'SOAPAction': config.protocols.tr069.soapAction,
    };
}

/**
 * Get random integer between min and max
 */
export function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Get random element from array
 */
export function randomElement(array) {
    return array[Math.floor(Math.random() * array.length)];
}

/**
 * Sleep for random duration (for realistic user behavior)
 */
export function randomSleep(minMs = 1000, maxMs = 5000) {
    const duration = randomInt(minMs, maxMs) / 1000;
    return duration;
}

export default config;
