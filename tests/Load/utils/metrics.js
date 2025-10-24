/**
 * K6 Load Testing - Custom Metrics
 * 
 * Define custom metrics for ACS-specific measurements
 */

import { Counter, Trend, Rate, Gauge } from 'k6/metrics';

/**
 * TR-069 Metrics
 */
export const tr069Metrics = {
    // Counters
    informMessages: new Counter('tr069_inform_messages_total'),
    getParameterRequests: new Counter('tr069_get_parameter_requests_total'),
    setParameterRequests: new Counter('tr069_set_parameter_requests_total'),
    connectionRequests: new Counter('tr069_connection_requests_total'),
    
    // Trends (timing)
    informDuration: new Trend('tr069_inform_duration'),
    getParameterDuration: new Trend('tr069_get_parameter_duration'),
    setParameterDuration: new Trend('tr069_set_parameter_duration'),
    
    // Rates (success/failure)
    informSuccess: new Rate('tr069_inform_success_rate'),
    parameterOperationSuccess: new Rate('tr069_parameter_operation_success_rate'),
};

/**
 * TR-369 USP Metrics
 */
export const tr369Metrics = {
    // Counters
    uspRecordsProcessed: new Counter('tr369_usp_records_total'),
    uspGetRequests: new Counter('tr369_usp_get_requests_total'),
    uspSetRequests: new Counter('tr369_usp_set_requests_total'),
    uspOperateRequests: new Counter('tr369_usp_operate_requests_total'),
    
    // Transport-specific
    httpTransportMessages: new Counter('tr369_http_transport_messages_total'),
    mqttTransportMessages: new Counter('tr369_mqtt_transport_messages_total'),
    wsTransportMessages: new Counter('tr369_websocket_transport_messages_total'),
    
    // Trends
    uspRecordDuration: new Trend('tr369_usp_record_duration'),
    mqttLatency: new Trend('tr369_mqtt_latency'),
    wsLatency: new Trend('tr369_websocket_latency'),
    
    // Rates
    uspOperationSuccess: new Rate('tr369_usp_operation_success_rate'),
    protobufEncodeSuccess: new Rate('tr369_protobuf_encode_success_rate'),
};

/**
 * REST API Metrics
 */
export const apiMetrics = {
    // Counters
    deviceListRequests: new Counter('api_device_list_requests_total'),
    deviceSearchRequests: new Counter('api_device_search_requests_total'),
    deviceCreateRequests: new Counter('api_device_create_requests_total'),
    deviceUpdateRequests: new Counter('api_device_update_requests_total'),
    deviceDeleteRequests: new Counter('api_device_delete_requests_total'),
    bulkOperations: new Counter('api_bulk_operations_total'),
    
    // Trends
    listDuration: new Trend('api_device_list_duration'),
    searchDuration: new Trend('api_device_search_duration'),
    createDuration: new Trend('api_device_create_duration'),
    
    // Rates
    apiSuccess: new Rate('api_request_success_rate'),
    validationErrors: new Rate('api_validation_error_rate'),
};

/**
 * Database Metrics
 */
export const databaseMetrics = {
    // Counters
    queryCount: new Counter('database_queries_total'),
    slowQueries: new Counter('database_slow_queries_total'),
    connectionErrors: new Counter('database_connection_errors_total'),
    
    // Trends
    queryDuration: new Trend('database_query_duration'),
    connectionWaitTime: new Trend('database_connection_wait_time'),
    
    // Gauge
    activeConnections: new Gauge('database_active_connections'),
    
    // Rates
    querySuccess: new Rate('database_query_success_rate'),
    cacheHitRate: new Rate('database_cache_hit_rate'),
};

/**
 * System Performance Metrics
 */
export const systemMetrics = {
    // Gauges
    cpuUsage: new Gauge('system_cpu_usage_percent'),
    memoryUsage: new Gauge('system_memory_usage_percent'),
    queueDepth: new Gauge('system_queue_depth'),
    
    // Counters
    queuedJobs: new Counter('system_queued_jobs_total'),
    processedJobs: new Counter('system_processed_jobs_total'),
    failedJobs: new Counter('system_failed_jobs_total'),
    
    // Trends
    jobProcessingTime: new Trend('system_job_processing_time'),
    
    // Rates
    jobSuccess: new Rate('system_job_success_rate'),
};

/**
 * Device Registration Metrics
 */
export const deviceMetrics = {
    // Counters
    registrations: new Counter('device_registrations_total'),
    provisioning: new Counter('device_provisioning_total'),
    firmwareUpgrades: new Counter('device_firmware_upgrades_total'),
    
    // Trends
    registrationDuration: new Trend('device_registration_duration'),
    provisioningDuration: new Trend('device_provisioning_duration'),
    
    // Rates
    registrationSuccess: new Rate('device_registration_success_rate'),
    autoDetectionSuccess: new Rate('device_auto_detection_success_rate'),
};

/**
 * Record TR-069 Inform message
 */
export function recordTr069Inform(duration, success) {
    tr069Metrics.informMessages.add(1);
    tr069Metrics.informDuration.add(duration);
    tr069Metrics.informSuccess.add(success ? 1 : 0);
}

/**
 * Record TR-069 Parameter operation
 */
export function recordTr069ParameterOp(type, duration, success) {
    if (type === 'get') {
        tr069Metrics.getParameterRequests.add(1);
        tr069Metrics.getParameterDuration.add(duration);
    } else if (type === 'set') {
        tr069Metrics.setParameterRequests.add(1);
        tr069Metrics.setParameterDuration.add(duration);
    }
    tr069Metrics.parameterOperationSuccess.add(success ? 1 : 0);
}

/**
 * Record TR-369 USP operation
 */
export function recordUspOperation(transport, duration, success) {
    tr369Metrics.uspRecordsProcessed.add(1);
    tr369Metrics.uspRecordDuration.add(duration);
    tr369Metrics.uspOperationSuccess.add(success ? 1 : 0);
    
    if (transport === 'http') {
        tr369Metrics.httpTransportMessages.add(1);
    } else if (transport === 'mqtt') {
        tr369Metrics.mqttTransportMessages.add(1);
        tr369Metrics.mqttLatency.add(duration);
    } else if (transport === 'websocket') {
        tr369Metrics.wsTransportMessages.add(1);
        tr369Metrics.wsLatency.add(duration);
    }
}

/**
 * Record API request
 */
export function recordApiRequest(endpoint, duration, statusCode) {
    const success = statusCode >= 200 && statusCode < 300;
    
    apiMetrics.apiSuccess.add(success ? 1 : 0);
    
    if (endpoint.includes('list')) {
        apiMetrics.deviceListRequests.add(1);
        apiMetrics.listDuration.add(duration);
    } else if (endpoint.includes('search')) {
        apiMetrics.deviceSearchRequests.add(1);
        apiMetrics.searchDuration.add(duration);
    } else if (endpoint.includes('create')) {
        apiMetrics.deviceCreateRequests.add(1);
        apiMetrics.createDuration.add(duration);
    }
    
    if (statusCode === 422) {
        apiMetrics.validationErrors.add(1);
    }
}

/**
 * Record device registration
 */
export function recordDeviceRegistration(duration, success) {
    deviceMetrics.registrations.add(1);
    deviceMetrics.registrationDuration.add(duration);
    deviceMetrics.registrationSuccess.add(success ? 1 : 0);
}

/**
 * Record database query
 */
export function recordDatabaseQuery(duration, success, slow = false) {
    databaseMetrics.queryCount.add(1);
    databaseMetrics.queryDuration.add(duration);
    databaseMetrics.querySuccess.add(success ? 1 : 0);
    
    if (slow) {
        databaseMetrics.slowQueries.add(1);
    }
}

export default {
    tr069Metrics,
    tr369Metrics,
    apiMetrics,
    databaseMetrics,
    systemMetrics,
    deviceMetrics,
    recordTr069Inform,
    recordTr069ParameterOp,
    recordUspOperation,
    recordApiRequest,
    recordDeviceRegistration,
    recordDatabaseQuery,
};
