<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\ProvisioningController;
use App\Http\Controllers\Api\FirmwareController;
use App\Http\Controllers\Api\DiagnosticsController;
use App\Http\Controllers\Api\UspController;
use App\Http\Controllers\Api\VoiceServiceController;
use App\Http\Controllers\Api\StorageServiceController;
use App\Http\Controllers\Api\ParameterDiscoveryController;
use App\Http\Controllers\Api\LanDeviceController;
use App\Http\Controllers\Api\IotDeviceController;
use App\Http\Controllers\Api\FemtocellController;
use App\Http\Controllers\Api\StbServiceController;
use App\Http\Controllers\Api\TR181Controller;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\StompMetricsController;
use App\Http\Controllers\Api\VendorLibraryController;

Route::prefix('v1')->middleware(\App\Http\Middleware\ApiKeyAuth::class)->group(function () {
    
    // Telemetry & Monitoring APIs
    Route::get('telemetry/current', [TelemetryController::class, 'current']);
    Route::get('telemetry/history', [TelemetryController::class, 'history']);
    Route::get('telemetry/summary', [TelemetryController::class, 'summary']);
    Route::get('telemetry/health', [TelemetryController::class, 'health']);
    
    // TR-262 STOMP Metrics APIs
    Route::get('stomp/metrics', [StompMetricsController::class, 'index']);
    Route::get('stomp/connections', [StompMetricsController::class, 'connections']);
    Route::get('stomp/throughput', [StompMetricsController::class, 'throughput']);
    Route::get('stomp/errors', [StompMetricsController::class, 'errors']);
    Route::get('stomp/broker-health', [StompMetricsController::class, 'brokerHealth']);
    
    // System Update APIs
    Route::get('system/status', [\App\Http\Controllers\SystemUpdateController::class, 'status']);
    Route::get('system/version', [\App\Http\Controllers\SystemUpdateController::class, 'versionInfo']);
    Route::get('system/health', [\App\Http\Controllers\SystemUpdateController::class, 'healthCheck']);
    Route::get('system/history', [\App\Http\Controllers\SystemUpdateController::class, 'history']);
    Route::post('system/update', [\App\Http\Controllers\SystemUpdateController::class, 'runUpdate']);
    
    // GitHub Release Update Management
    Route::get('system/updates/pending', [\App\Http\Controllers\SystemUpdateController::class, 'pendingUpdates']);
    Route::post('system/updates/{id}/approve', [\App\Http\Controllers\SystemUpdateController::class, 'approveUpdate']);
    Route::post('system/updates/{id}/reject', [\App\Http\Controllers\SystemUpdateController::class, 'rejectUpdate']);
    Route::post('system/updates/{id}/schedule', [\App\Http\Controllers\SystemUpdateController::class, 'scheduleUpdate']);
    Route::post('system/updates/{id}/apply', [\App\Http\Controllers\SystemUpdateController::class, 'applyUpdate']);
    Route::get('system/updates/{id}/validate', [\App\Http\Controllers\SystemUpdateController::class, 'validateStagedUpdate']);
    
    Route::apiResource('devices', DeviceController::class);
    Route::post('devices/{device}/provision', [ProvisioningController::class, 'provisionDevice']);
    Route::post('devices/{device}/parameters/get', [ProvisioningController::class, 'getParameters']);
    Route::post('devices/{device}/parameters/set', [ProvisioningController::class, 'setParameters']);
    Route::post('devices/{device}/reboot', [ProvisioningController::class, 'rebootDevice']);
    Route::post('devices/{device}/connection-request', [ProvisioningController::class, 'connectionRequest']);
    
    Route::apiResource('firmware', FirmwareController::class);
    Route::post('firmware/{firmware}/deploy', [FirmwareController::class, 'deploy']);
    
    Route::get('tasks', [ProvisioningController::class, 'listTasks']);
    Route::get('tasks/{task}', [ProvisioningController::class, 'getTask']);
    
    // Diagnostics TR-143
    Route::post('devices/{device}/diagnostics/ping', [DiagnosticsController::class, 'ping']);
    Route::post('devices/{device}/diagnostics/traceroute', [DiagnosticsController::class, 'traceroute']);
    Route::post('devices/{device}/diagnostics/download', [DiagnosticsController::class, 'download']);
    Route::post('devices/{device}/diagnostics/upload', [DiagnosticsController::class, 'upload']);
    Route::post('devices/{device}/diagnostics/udpecho', [DiagnosticsController::class, 'udpEcho']);
    Route::get('devices/{device}/diagnostics', [DiagnosticsController::class, 'listDeviceDiagnostics']);
    Route::get('diagnostics', [DiagnosticsController::class, 'index']);
    Route::get('diagnostics/{diagnostic}', [DiagnosticsController::class, 'getResults']);
    
    // USP TR-369 Operations
    Route::post('usp/devices/{device}/get-params', [UspController::class, 'getParameters']);
    Route::post('usp/devices/{device}/set-params', [UspController::class, 'setParameters']);
    Route::post('usp/devices/{device}/operate', [UspController::class, 'operate']);
    Route::post('usp/devices/{device}/add-object', [UspController::class, 'addObject']);
    Route::post('usp/devices/{device}/delete-object', [UspController::class, 'deleteObject']);
    Route::post('usp/devices/{device}/reboot', [UspController::class, 'reboot']);
    
    // USP TR-369 Event Subscriptions
    Route::post('usp/devices/{device}/subscribe', [UspController::class, 'createSubscription']);
    Route::get('usp/devices/{device}/subscriptions', [UspController::class, 'listSubscriptions']);
    Route::delete('usp/devices/{device}/subscriptions/{subscription}', [UspController::class, 'deleteSubscription']);
    
    // TR-104 VoIP Service Management
    Route::post('devices/{device}/voice-services', [VoiceServiceController::class, 'store']);
    Route::apiResource('voice-services', VoiceServiceController::class)->except(['store']);
    Route::post('voice-services/{service}/provision', [VoiceServiceController::class, 'provisionService']);
    Route::post('voice-services/{service}/sip-profiles', [VoiceServiceController::class, 'createSipProfile']);
    Route::post('sip-profiles/{profile}/voip-lines', [VoiceServiceController::class, 'createVoipLine']);
    Route::post('voice-services/{service}/voip-lines', [VoiceServiceController::class, 'createVoipLine']);
    Route::get('voice-services/stats/overview', [VoiceServiceController::class, 'getStatistics']);
    
    // TR-140 Storage Service Management
    Route::post('devices/{device}/storage-services', [StorageServiceController::class, 'store']);
    Route::apiResource('storage-services', StorageServiceController::class)->except(['store']);
    Route::post('storage-services/{service}/provision', [StorageServiceController::class, 'provisionService']);
    Route::post('storage-services/{service}/volumes', [StorageServiceController::class, 'createVolume']);
    Route::post('storage-services/{service}/file-servers', [StorageServiceController::class, 'createFileServer']);
    Route::get('storage-services/stats/overview', [StorageServiceController::class, 'getStatistics']);
    
    // TR-111 Parameter Discovery
    Route::post('devices/{device}/discover-parameters', [ParameterDiscoveryController::class, 'discoverParameters']);
    Route::get('devices/{device}/capabilities', [ParameterDiscoveryController::class, 'getCapabilities']);
    Route::get('devices/{device}/capabilities/stats', [ParameterDiscoveryController::class, 'getStats']);
    Route::get('devices/{device}/capabilities/path', [ParameterDiscoveryController::class, 'getCapabilityByPath']);
    
    // TR-64 LAN-Side Configuration
    Route::get('devices/{device}/lan-devices', [LanDeviceController::class, 'index']);
    Route::post('devices/{device}/lan-devices/ssdp', [LanDeviceController::class, 'processSsdpAnnouncement']);
    Route::post('lan-devices/{lanDevice}/soap-action', [LanDeviceController::class, 'invokeSoapAction']);
    
    // TR-181 IoT Extension
    Route::get('devices/{device}/smart-home-devices', [IotDeviceController::class, 'listDevices']);
    Route::post('devices/{device}/smart-home-devices', [IotDeviceController::class, 'provisionDevice']);
    Route::patch('smart-home-devices/{smartDevice}/state', [IotDeviceController::class, 'updateState']);
    Route::get('devices/{device}/iot-services', [IotDeviceController::class, 'listServices']);
    Route::post('devices/{device}/iot-services', [IotDeviceController::class, 'createService']);
    
    // TR-196 Femtocell
    Route::post('devices/{device}/femtocell/configure', [FemtocellController::class, 'configure']);
    Route::post('femtocell-configs/{config}/neighbor-cells', [FemtocellController::class, 'addNeighborCell']);
    Route::post('femtocell-configs/{config}/scan', [FemtocellController::class, 'scanEnvironment']);
    
    // TR-135 STB/IPTV
    Route::post('devices/{device}/stb-services', [StbServiceController::class, 'provisionService']);
    Route::post('stb-services/{service}/sessions', [StbServiceController::class, 'startSession']);
    Route::patch('streaming-sessions/{session}/qos', [StbServiceController::class, 'updateQos']);
    
    // TR-181 Device:2 Data Model - Complete Implementation
    Route::get('devices/{device}/tr181/parameters', [TR181Controller::class, 'getAllParameters']);
    Route::post('devices/{device}/tr181/parameters', [TR181Controller::class, 'setParameters']);
    Route::get('devices/{device}/tr181/parameter', [TR181Controller::class, 'getParameter']);
    Route::get('devices/{device}/tr181/{namespace}', [TR181Controller::class, 'getNamespace']);
    Route::get('devices/{device}/tr181/device-info', [TR181Controller::class, 'getDeviceInfo']);
    Route::get('devices/{device}/tr181/management-server', [TR181Controller::class, 'getManagementServer']);
    Route::put('devices/{device}/tr181/management-server', [TR181Controller::class, 'updateManagementServer']);
    Route::get('devices/{device}/tr181/wifi', [TR181Controller::class, 'getWiFi']);
    Route::get('devices/{device}/tr181/lan', [TR181Controller::class, 'getLAN']);
    Route::get('devices/{device}/tr181/hosts', [TR181Controller::class, 'getHosts']);
    Route::get('devices/{device}/tr181/dhcp', [TR181Controller::class, 'getDHCP']);
    Route::get('tr181/validate', [TR181Controller::class, 'validateParameter']);
    
    // Vendor Library & Compatibility Matrix
    Route::prefix('vendors')->group(function () {
        Route::get('manufacturers', [VendorLibraryController::class, 'getManufacturers']);
        Route::get('manufacturers/{id}', [VendorLibraryController::class, 'getManufacturer']);
        Route::get('products', [VendorLibraryController::class, 'getProducts']);
        Route::get('products/{id}', [VendorLibraryController::class, 'getProduct']);
        Route::get('products/{id}/compatibility-matrix', [VendorLibraryController::class, 'getProductCompatibilityMatrix']);
        Route::get('products/{id}/quirks', [VendorLibraryController::class, 'getProductQuirks']);
        Route::get('quirks', [VendorLibraryController::class, 'getQuirks']);
        Route::get('templates', [VendorLibraryController::class, 'getTemplates']);
        Route::get('templates/{id}', [VendorLibraryController::class, 'getTemplate']);
        Route::post('detect', [VendorLibraryController::class, 'detectVendor']);
        Route::post('compatibility/check', [VendorLibraryController::class, 'checkCompatibility']);
        Route::get('stats', [VendorLibraryController::class, 'getStatistics']);
        
        // Bulk Operations
        Route::prefix('bulk')->group(function () {
            Route::post('detect', [VendorLibraryController::class, 'bulkDetectVendor']);
            Route::post('apply-template', [VendorLibraryController::class, 'bulkApplyTemplate']);
            Route::post('firmware-check', [VendorLibraryController::class, 'bulkFirmwareCompatibilityCheck']);
        });
    });
});
