/**
 * K6 to Prometheus Metrics Exporter
 * 
 * This script reads K6 JSON output and exposes metrics in Prometheus format
 * via HTTP endpoint that Prometheus can scrape.
 * 
 * Usage:
 *   # Terminal 1: Run K6 test with JSON output
 *   k6 run --out json=test-results.json tests/Load/scenarios/mixed.js
 * 
 *   # Terminal 2: Start Prometheus exporter
 *   node tests/Load/utils/prometheus-exporter.js test-results.json
 * 
 * Prometheus will scrape metrics from http://localhost:9091/metrics
 */

const http = require('http');
const fs = require('fs');
const readline = require('readline');

const METRICS_PORT = process.env.K6_METRICS_PORT || 9091;
const JSON_FILE = process.argv[2] || 'test-results.json';

// Metrics storage
const metrics = {
    // Built-in K6 metrics
    http_req_duration: [],
    http_req_failed: 0,
    http_reqs: 0,
    vus: 0,
    iterations: 0,
    
    // Custom metrics
    tr069_inform_duration: [],
    tr069_inform_success_rate: 0,
    tr369_usp_operation_duration: [],
    tr369_usp_operation_success_rate: 0,
    api_request_duration: [],
    api_request_success_rate: 0,
    
    // Transport-specific
    tr369_http_messages: 0,
    tr369_mqtt_messages: 0,
    tr369_websocket_messages: 0,
};

/**
 * Convert metrics to Prometheus exposition format
 */
function toPrometheusFormat() {
    const lines = [];
    const timestamp = Date.now();
    
    // Helper to calculate percentiles
    const percentile = (arr, p) => {
        if (!arr.length) return 0;
        const sorted = arr.slice().sort((a, b) => a - b);
        const index = Math.ceil((p / 100) * sorted.length) - 1;
        return sorted[index] || 0;
    };
    
    // Helper to calculate average
    const avg = (arr) => arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0;
    
    // HTTP Request Duration (histogram)
    if (metrics.http_req_duration.length) {
        lines.push('# HELP k6_http_req_duration HTTP request duration in milliseconds');
        lines.push('# TYPE k6_http_req_duration summary');
        lines.push(`k6_http_req_duration{quantile="0.5"} ${percentile(metrics.http_req_duration, 50)}`);
        lines.push(`k6_http_req_duration{quantile="0.95"} ${percentile(metrics.http_req_duration, 95)}`);
        lines.push(`k6_http_req_duration{quantile="0.99"} ${percentile(metrics.http_req_duration, 99)}`);
        lines.push(`k6_http_req_duration_sum ${metrics.http_req_duration.reduce((a, b) => a + b, 0)}`);
        lines.push(`k6_http_req_duration_count ${metrics.http_req_duration.length}`);
    }
    
    // HTTP Requests Total
    lines.push('# HELP k6_http_reqs_total Total number of HTTP requests');
    lines.push('# TYPE k6_http_reqs_total counter');
    lines.push(`k6_http_reqs_total ${metrics.http_reqs}`);
    
    // HTTP Request Failures
    lines.push('# HELP k6_http_req_failed_total Total number of failed HTTP requests');
    lines.push('# TYPE k6_http_req_failed_total counter');
    lines.push(`k6_http_req_failed_total ${metrics.http_req_failed}`);
    
    // Virtual Users
    lines.push('# HELP k6_vus Current number of virtual users');
    lines.push('# TYPE k6_vus gauge');
    lines.push(`k6_vus ${metrics.vus}`);
    
    // Iterations
    lines.push('# HELP k6_iterations_total Total number of VU iterations');
    lines.push('# TYPE k6_iterations_total counter');
    lines.push(`k6_iterations_total ${metrics.iterations}`);
    
    // TR-069 Inform Duration
    if (metrics.tr069_inform_duration.length) {
        lines.push('# HELP k6_tr069_inform_duration TR-069 Inform duration in milliseconds');
        lines.push('# TYPE k6_tr069_inform_duration summary');
        lines.push(`k6_tr069_inform_duration{quantile="0.95"} ${percentile(metrics.tr069_inform_duration, 95)}`);
        lines.push(`k6_tr069_inform_duration{quantile="0.99"} ${percentile(metrics.tr069_inform_duration, 99)}`);
        lines.push(`k6_tr069_inform_duration_count ${metrics.tr069_inform_duration.length}`);
    }
    
    // TR-069 Success Rate
    lines.push('# HELP k6_tr069_inform_success_rate TR-069 Inform success rate');
    lines.push('# TYPE k6_tr069_inform_success_rate gauge');
    lines.push(`k6_tr069_inform_success_rate ${metrics.tr069_inform_success_rate}`);
    
    // TR-369 USP Duration
    if (metrics.tr369_usp_operation_duration.length) {
        lines.push('# HELP k6_tr369_usp_operation_duration TR-369 USP operation duration in milliseconds');
        lines.push('# TYPE k6_tr369_usp_operation_duration summary');
        lines.push(`k6_tr369_usp_operation_duration{quantile="0.95"} ${percentile(metrics.tr369_usp_operation_duration, 95)}`);
        lines.push(`k6_tr369_usp_operation_duration{quantile="0.99"} ${percentile(metrics.tr369_usp_operation_duration, 99)}`);
        lines.push(`k6_tr369_usp_operation_duration_count ${metrics.tr369_usp_operation_duration.length}`);
    }
    
    // TR-369 Transport Messages
    lines.push('# HELP k6_tr369_http_messages_total TR-369 HTTP transport messages');
    lines.push('# TYPE k6_tr369_http_messages_total counter');
    lines.push(`k6_tr369_http_messages_total ${metrics.tr369_http_messages}`);
    
    lines.push('# HELP k6_tr369_mqtt_messages_total TR-369 MQTT transport messages');
    lines.push('# TYPE k6_tr369_mqtt_messages_total counter');
    lines.push(`k6_tr369_mqtt_messages_total ${metrics.tr369_mqtt_messages}`);
    
    lines.push('# HELP k6_tr369_websocket_messages_total TR-369 WebSocket transport messages');
    lines.push('# TYPE k6_tr369_websocket_messages_total counter');
    lines.push(`k6_tr369_websocket_messages_total ${metrics.tr369_websocket_messages}`);
    
    // API Request Duration
    if (metrics.api_request_duration.length) {
        lines.push('# HELP k6_api_request_duration API request duration in milliseconds');
        lines.push('# TYPE k6_api_request_duration summary');
        lines.push(`k6_api_request_duration{quantile="0.95"} ${percentile(metrics.api_request_duration, 95)}`);
        lines.push(`k6_api_request_duration{quantile="0.99"} ${percentile(metrics.api_request_duration, 99)}`);
        lines.push(`k6_api_request_duration_count ${metrics.api_request_duration.length}`);
    }
    
    return lines.join('\n') + '\n';
}

/**
 * Parse K6 JSON output line by line
 */
function parseK6Output(line) {
    try {
        const data = JSON.parse(line);
        
        // K6 JSON format: { type: 'Metric'|'Point', metric: '...', data: {...} }
        if (data.type === 'Point') {
            const { metric, data: pointData } = data;
            
            switch (metric) {
                case 'http_req_duration':
                    metrics.http_req_duration.push(pointData.value);
                    break;
                case 'http_req_failed':
                    if (pointData.value > 0) metrics.http_req_failed++;
                    break;
                case 'http_reqs':
                    metrics.http_reqs++;
                    break;
                case 'vus':
                    metrics.vus = pointData.value;
                    break;
                case 'iterations':
                    metrics.iterations++;
                    break;
                case 'tr069_inform_duration':
                    metrics.tr069_inform_duration.push(pointData.value);
                    break;
                case 'tr369_usp_operation_duration':
                    metrics.tr369_usp_operation_duration.push(pointData.value);
                    break;
                case 'api_request_duration':
                    metrics.api_request_duration.push(pointData.value);
                    break;
            }
        }
    } catch (err) {
        // Ignore parse errors (summary lines, etc.)
    }
}

/**
 * Watch K6 JSON output file and update metrics in real-time
 */
function watchJsonFile(filePath) {
    console.log(`[Prometheus Exporter] Watching ${filePath} for K6 metrics...`);
    
    const stream = fs.createReadStream(filePath);
    const rl = readline.createInterface({ input: stream });
    
    rl.on('line', parseK6Output);
    
    // Watch file for new lines (tail -f behavior)
    fs.watchFile(filePath, () => {
        const newStream = fs.createReadStream(filePath, { start: stream.bytesRead });
        const newRl = readline.createInterface({ input: newStream });
        newRl.on('line', parseK6Output);
    });
}

/**
 * Start HTTP server to expose Prometheus metrics
 */
function startMetricsServer(port) {
    const server = http.createServer((req, res) => {
        if (req.url === '/metrics') {
            res.writeHead(200, { 'Content-Type': 'text/plain; version=0.0.4' });
            res.end(toPrometheusFormat());
        } else if (req.url === '/health') {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ status: 'healthy', metrics_count: Object.keys(metrics).length }));
        } else {
            res.writeHead(404);
            res.end('Not Found');
        }
    });
    
    server.listen(port, () => {
        console.log(`[Prometheus Exporter] Metrics server running on http://localhost:${port}/metrics`);
        console.log(`[Prometheus Exporter] Health check: http://localhost:${port}/health`);
    });
}

// Main
if (require.main === module) {
    if (!fs.existsSync(JSON_FILE)) {
        console.error(`Error: JSON file not found: ${JSON_FILE}`);
        console.log('Usage: node prometheus-exporter.js <k6-json-output-file>');
        process.exit(1);
    }
    
    watchJsonFile(JSON_FILE);
    startMetricsServer(METRICS_PORT);
}

module.exports = { toPrometheusFormat, parseK6Output };
