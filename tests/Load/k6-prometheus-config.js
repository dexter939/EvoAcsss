/**
 * K6 Prometheus Remote Write Configuration
 * 
 * This configuration enables K6 to export metrics to Prometheus
 * using the Prometheus Remote Write protocol.
 * 
 * Usage:
 *   K6_PROMETHEUS_RW_SERVER_URL=http://localhost:9090/api/v1/write \
 *   k6 run --out experimental-prometheus-rw tests/Load/scenarios/mixed.js
 * 
 * Note: Requires K6 v0.47.0+ with experimental Prometheus Remote Write support
 */

export const prometheusConfig = {
    // Prometheus Remote Write endpoint
    remoteWriteUrl: __ENV.K6_PROMETHEUS_RW_SERVER_URL || 'http://localhost:9090/api/v1/write',
    
    // Flush interval (how often to send metrics to Prometheus)
    flushInterval: '10s',
    
    // Additional labels to add to all metrics
    labels: {
        project: 'acs',
        test_type: 'load',
        environment: __ENV.K6_ENV || 'staging',
    },
    
    // Metrics to include (by default all built-in + custom metrics)
    includeMetrics: [
        // K6 built-in metrics
        'http_req_duration',
        'http_req_failed',
        'http_reqs',
        'vus',
        'vus_max',
        'iterations',
        'data_sent',
        'data_received',
        
        // Custom ACS metrics
        'tr069_inform_duration',
        'tr069_inform_success_rate',
        'tr369_usp_operation_duration',
        'tr369_usp_operation_success_rate',
        'api_request_duration',
        'api_request_success_rate',
        'device_registration_success_rate',
        
        // Transport-specific metrics
        'tr369_http_transport_messages',
        'tr369_mqtt_transport_messages',
        'tr369_websocket_transport_messages',
        'tr369_mqtt_latency',
        'ws_session_duration',
    ],
};

/**
 * Alternative: Prometheus HTTP endpoint (for scraping)
 * 
 * If Remote Write is not available, you can use K6's summary export
 * and expose metrics via HTTP endpoint that Prometheus can scrape.
 */
export const prometheusHttpConfig = {
    // Enable summary handler
    enabled: true,
    
    // Port for metrics HTTP server
    port: __ENV.K6_METRICS_PORT || 9091,
    
    // Path for metrics endpoint
    path: '/metrics',
    
    // Update interval
    updateInterval: '5s',
};

export default {
    prometheus: prometheusConfig,
    http: prometheusHttpConfig,
};
