<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TR069Controller;
use App\Http\Controllers\UspController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AcsController;
use App\Http\Controllers\DataModelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Acs\VendorLibraryWebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AlarmsController;

require __DIR__.'/auth.php';

// Health check endpoint for load balancers/Docker
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response('OK', 200)->header('Content-Type', 'text/plain');
    } catch (\Exception $e) {
        return response('UNHEALTHY', 503)->header('Content-Type', 'text/plain');
    }
});

// Prometheus metrics endpoint
Route::get('/metrics', [App\Http\Controllers\Api\MetricsController::class, 'index']);

// Home - Redirect to Dashboard
Route::get('/', function () {
    return redirect()->route('acs.dashboard');
});

// API JSON Dashboard (legacy)
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// TR-069 Endpoints (Public)
Route::post('/tr069', [TR069Controller::class, 'handleInform'])->name('tr069.inform');
Route::post('/tr069/empty', [TR069Controller::class, 'handleEmpty'])->name('tr069.empty');

// TR-369 USP Endpoints (Public)
Route::match(['get', 'post'], '/usp', [UspController::class, 'handleUspMessage'])->name('usp.message');

// ACS Web Dashboard (Protected Routes)
Route::prefix('acs')->name('acs.')->middleware('auth')->group(function () {
    Route::get('/dashboard', [AcsController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/stats-api', [AcsController::class, 'dashboardStatsApi'])->name('dashboard.stats');
    
    // Dispositivi
    Route::get('/devices', [AcsController::class, 'devices'])->name('devices');
    Route::get('/devices/datatable', [AcsController::class, 'devicesDataTable'])->name('devices.datatable');
    Route::post('/devices', [AcsController::class, 'storeDevice'])->name('devices.store');
    Route::get('/devices/unassigned-list', [AcsController::class, 'getUnassignedDevices'])->name('devices.unassigned-list');
    Route::get('/devices/{id}', [AcsController::class, 'showDevice'])->name('devices.show');
    Route::get('/devices/{id}/parameters', [AcsController::class, 'getDeviceParameters'])->name('devices.parameters');
    Route::get('/devices/{id}/history', [AcsController::class, 'getDeviceHistory'])->name('devices.history');
    Route::put('/devices/{id}', [AcsController::class, 'updateDevice'])->name('devices.update');
    Route::delete('/devices/{id}', [AcsController::class, 'destroyDevice'])->name('devices.destroy');
    Route::post('/devices/{id}/provision', [AcsController::class, 'provisionDevice'])->name('devices.provision');
    Route::post('/devices/{id}/reboot', [AcsController::class, 'rebootDevice'])->name('devices.reboot');
    Route::post('/devices/{id}/connection-request', [AcsController::class, 'connectionRequest'])->name('devices.connection-request');
    Route::post('/devices/{id}/diagnostics/{type}', [AcsController::class, 'runDiagnostic'])->name('devices.diagnostic');
    Route::post('/devices/{id}/diagnostics', [AcsController::class, 'runDiagnosticTest'])->name('devices.diagnostics.run');
    Route::get('/devices/{id}/diagnostics/history', [AcsController::class, 'getDiagnosticHistory'])->name('devices.diagnostics.history');
    Route::post('/devices/{id}/get-parameters', [AcsController::class, 'getDeviceParametersFromDevice'])->name('devices.get-parameters');
    Route::post('/devices/{id}/set-parameters', [AcsController::class, 'setDeviceParametersOnDevice'])->name('devices.set-parameters');
    Route::get('/diagnostics/{id}/results', [AcsController::class, 'getDiagnosticResults'])->name('diagnostics.results');
    Route::get('/devices/{id}/network-map', [AcsController::class, 'networkMap'])->name('devices.network-map');
    Route::post('/devices/{id}/trigger-network-scan', [AcsController::class, 'triggerNetworkScan'])->name('devices.trigger-network-scan');
    Route::post('/devices/{id}/assign-service', [AcsController::class, 'assignDeviceToService'])->name('devices.assign-service');
    Route::post('/devices/{id}/assign-data-model', [AcsController::class, 'assignDataModel'])->name('devices.assign-data-model');
    
    // NAT Traversal: Pending Commands Management
    Route::post('/pending-commands/{id}/retry', [AcsController::class, 'retryPendingCommand'])->name('pending-commands.retry');
    Route::post('/pending-commands/{id}/cancel', [AcsController::class, 'cancelPendingCommand'])->name('pending-commands.cancel');
    
    // USP Event Subscriptions
    Route::get('/devices/{id}/subscriptions', [AcsController::class, 'subscriptions'])->name('devices.subscriptions');
    Route::post('/devices/{id}/subscriptions', [AcsController::class, 'storeSubscription'])->name('devices.subscriptions.store');
    Route::delete('/devices/{id}/subscriptions/{subscriptionId}', [AcsController::class, 'destroySubscription'])->name('devices.subscriptions.destroy');
    
    // Provisioning
    Route::get('/provisioning', [AcsController::class, 'provisioning'])->name('provisioning');
    Route::get('/advanced-provisioning', [AcsController::class, 'advancedProvisioning'])->name('advanced-provisioning');
    Route::get('/provisioning/statistics', [AcsController::class, 'provisioningStatistics'])->name('provisioning.statistics');
    Route::post('/provisioning/bulk', [AcsController::class, 'bulkProvisioning'])->name('provisioning.bulk');
    Route::post('/provisioning/schedule', [AcsController::class, 'scheduleProvisioning'])->name('provisioning.schedule');
    Route::post('/provisioning/rollback/{deviceId}/{version}', [AcsController::class, 'rollbackConfiguration'])->name('provisioning.rollback');
    
    // Firmware
    Route::get('/firmware', [AcsController::class, 'firmware'])->name('firmware');
    Route::post('/firmware/upload', [AcsController::class, 'uploadFirmware'])->name('firmware.upload');
    Route::post('/firmware/{id}/deploy', [AcsController::class, 'deployFirmware'])->name('firmware.deploy');
    
    // Task Queue
    Route::get('/tasks', [AcsController::class, 'tasks'])->name('tasks');
    
    // Profili Configurazione CRUD
    Route::get('/profiles', [AcsController::class, 'profiles'])->name('profiles');
    Route::post('/profiles', [AcsController::class, 'storeProfile'])->name('profiles.store');
    Route::put('/profiles/{id}', [AcsController::class, 'updateProfile'])->name('profiles.update');
    Route::delete('/profiles/{id}', [AcsController::class, 'destroyProfile'])->name('profiles.destroy');
    
    // AI Configuration Assistant Dashboard
    Route::get('/ai-assistant', [AcsController::class, 'aiAssistant'])->name('ai-assistant');
    Route::post('/profiles/ai-generate', [AcsController::class, 'aiGenerateProfile'])->name('profiles.ai-generate');
    Route::post('/profiles/{id}/ai-validate', [AcsController::class, 'aiValidateProfile'])->name('profiles.ai-validate');
    Route::post('/profiles/{id}/ai-optimize', [AcsController::class, 'aiOptimizeProfile'])->name('profiles.ai-optimize');
    
    // AI Diagnostic Troubleshooting
    Route::post('/diagnostics/{diagnosticId}/ai-analyze', [AcsController::class, 'aiAnalyzeDiagnostic'])->name('diagnostics.ai-analyze');
    Route::post('/devices/{deviceId}/ai-analyze-diagnostics', [AcsController::class, 'aiAnalyzeDeviceDiagnostics'])->name('devices.ai-analyze-diagnostics');
    
    // Diagnostics (TR-143)
    Route::get('/diagnostics', [AcsController::class, 'diagnostics'])->name('diagnostics');
    Route::get('/diagnostics/{id}/details', [AcsController::class, 'diagnosticDetails'])->name('diagnostics.details');
    
    // Network Topology Map
    Route::get('/network-topology', [AcsController::class, 'networkTopology'])->name('network-topology');
    
    // Performance Monitoring
    Route::get('/performance-monitoring', [AcsController::class, 'performanceMonitoring'])->name('performance-monitoring');
    Route::get('/performance/metrics', [AcsController::class, 'performanceMetrics'])->name('performance.metrics');
    
    // Advanced Monitoring & Alerting
    Route::get('/advanced-monitoring', [AcsController::class, 'advancedMonitoring'])->name('advanced-monitoring');
    Route::get('/advanced-monitoring/data', [AcsController::class, 'advancedMonitoringData'])->name('advanced-monitoring.data');
    Route::post('/advanced-monitoring/create-rule', [AcsController::class, 'createAlertRule'])->name('advanced-monitoring.create-rule');
    Route::delete('/advanced-monitoring/rules/{id}', [AcsController::class, 'deleteAlertRule'])->name('advanced-monitoring.delete-rule');
    
    // Security Hardening Dashboard
    Route::get('/security', [AcsController::class, 'securityDashboard'])->name('security');
    Route::get('/security/data', [AcsController::class, 'securityDashboardData'])->name('security.data');
    Route::post('/security/block-ip', [AcsController::class, 'blockIpAddress'])->name('security.block-ip');
    Route::post('/security/unblock-ip', [AcsController::class, 'unblockIpAddress'])->name('security.unblock-ip');
    
    // Users Management (RBAC)
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
    
    // Roles Management (RBAC)
    Route::middleware(['permission:roles.manage'])->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/{id}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
    });
    
    // User Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.update-info');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
    
    // Alarms & Monitoring (RBAC Protected)
    Route::middleware(['permission:alarms.view'])->group(function () {
        Route::get('/alarms', [AlarmsController::class, 'index'])->name('alarms');
        Route::get('/alarms/stats', [AlarmsController::class, 'getStats'])->name('alarms.stats');
        Route::get('/alarms/stream', [AlarmsController::class, 'stream'])->name('alarms.stream');
    });
    
    Route::middleware(['permission:alarms.manage'])->group(function () {
        Route::post('/alarms/{id}/acknowledge', [AlarmsController::class, 'acknowledge'])->name('alarms.acknowledge');
        Route::post('/alarms/{id}/clear', [AlarmsController::class, 'clear'])->name('alarms.clear');
        Route::post('/alarms/bulk-acknowledge', [AlarmsController::class, 'bulkAcknowledge'])->name('alarms.bulk-acknowledge');
        Route::post('/alarms/bulk-clear', [AlarmsController::class, 'bulkClear'])->name('alarms.bulk-clear');
    });
    
    // Router Manufacturers & Products Database
    Route::get('/manufacturers', [AcsController::class, 'manufacturers'])->name('manufacturers');
    Route::get('/manufacturers/{id}/products', [AcsController::class, 'manufacturerProducts'])->name('manufacturers.products');
    
    // VoIP Services (TR-104)
    Route::get('/voip', [AcsController::class, 'voip'])->name('voip');
    Route::get('/voip/{deviceId}', [AcsController::class, 'voipDevice'])->name('voip.device');
    Route::post('/voip/{deviceId}/configure', [AcsController::class, 'voipConfigure'])->name('voip.configure');
    
    // Storage/NAS Services (TR-140)
    Route::get('/storage', [AcsController::class, 'storage'])->name('storage');
    Route::get('/storage/{deviceId}', [AcsController::class, 'storageDevice'])->name('storage.device');
    Route::post('/storage/{deviceId}/configure', [AcsController::class, 'storageConfigure'])->name('storage.configure');
    
    // IoT Devices (TR-181)
    Route::get('/iot', [AcsController::class, 'iot'])->name('iot');
    Route::get('/iot/{deviceId}', [AcsController::class, 'iotDevice'])->name('iot.device');
    Route::post('/iot/{deviceId}/control', [AcsController::class, 'iotControl'])->name('iot.control');
    
    // LAN Devices (TR-64)
    Route::get('/lan-devices', [AcsController::class, 'lanDevices'])->name('lan-devices');
    Route::get('/lan-devices/{deviceId}', [AcsController::class, 'lanDeviceDetail'])->name('lan-devices.detail');
    
    // Femtocell RF Management (TR-196)
    Route::get('/femtocell', [AcsController::class, 'femtocell'])->name('femtocell');
    Route::get('/femtocell/{deviceId}', [AcsController::class, 'femtocellDevice'])->name('femtocell.device');
    Route::post('/femtocell/{deviceId}/configure', [AcsController::class, 'femtocellConfigure'])->name('femtocell.configure');
    
    // STB/IPTV Services (TR-135)
    Route::get('/stb', [AcsController::class, 'stb'])->name('stb');
    Route::get('/stb/{deviceId}', [AcsController::class, 'stbDevice'])->name('stb.device');
    
    // System Updates & Auto-Deploy
    Route::get('/system-updates', [\App\Http\Controllers\SystemUpdateController::class, 'dashboard'])->name('system-updates');
    Route::post('/system-updates/run', [\App\Http\Controllers\SystemUpdateController::class, 'runUpdate'])->name('system-updates.run');
    Route::post('/stb/{deviceId}/configure', [AcsController::class, 'stbConfigure'])->name('stb.configure');
    
    // System Updates Dashboard
    Route::get('/updates', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'index'])->name('updates.index');
    Route::get('/updates/{id}', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'show'])->name('updates.show');
    Route::post('/updates/check', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'checkForUpdates'])->name('updates.check');
    Route::post('/updates/{id}/approve', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'approve'])->name('updates.approve');
    Route::post('/updates/{id}/reject', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'reject'])->name('updates.reject');
    Route::post('/updates/{id}/schedule', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'schedule'])->name('updates.schedule');
    Route::post('/updates/{id}/apply', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'apply'])->name('updates.apply');
    Route::get('/updates/{id}/validate', [\App\Http\Controllers\SystemUpdatesDashboardController::class, 'validate'])->name('updates.validate');
    
    // Parameter Discovery (TR-111)
    Route::get('/parameters', [AcsController::class, 'parameters'])->name('parameters');
    Route::get('/parameters/{deviceId}', [AcsController::class, 'parametersDevice'])->name('parameters.device');
    Route::post('/parameters/{deviceId}/discover', [AcsController::class, 'parametersDiscover'])->name('parameters.discover');
    
    // Data Models (TR-069/369)
    Route::get('/data-models', [DataModelController::class, 'index'])->name('data-models');
    Route::get('/data-models/{id}/parameters', [DataModelController::class, 'showParameters'])->name('data-models.parameters');
    
    // Multi-tenant Customers & Services
    Route::get('/customers', [AcsController::class, 'customers'])->name('customers');
    Route::get('/customers/{customerId}', [AcsController::class, 'customerDetail'])->name('customers.detail');
    Route::post('/customers', [AcsController::class, 'storeCustomer'])->name('customers.store');
    Route::put('/customers/{customerId}', [AcsController::class, 'updateCustomer'])->name('customers.update');
    Route::delete('/customers/{customerId}', [AcsController::class, 'destroyCustomer'])->name('customers.destroy');
    Route::get('/customers/{customerId}/services-list', [AcsController::class, 'getCustomerServices'])->name('customers.services-list');
    
    Route::get('/services/{serviceId}', [AcsController::class, 'serviceDetail'])->name('services.detail');
    Route::post('/services', [AcsController::class, 'storeService'])->name('services.store');
    Route::put('/services/{serviceId}', [AcsController::class, 'updateService'])->name('services.update');
    Route::delete('/services/{serviceId}', [AcsController::class, 'destroyService'])->name('services.destroy');
    Route::post('/services/{serviceId}/assign-devices', [AcsController::class, 'assignMultipleDevices'])->name('services.assign-devices');
    
    // Vendor Library & Compatibility Matrix
    Route::prefix('vendors')->name('vendors.')->group(function () {
        Route::get('/', [VendorLibraryWebController::class, 'index'])->name('index');
        Route::get('/manufacturers', [VendorLibraryWebController::class, 'manufacturers'])->name('manufacturers');
        Route::get('/manufacturers/{id}', [VendorLibraryWebController::class, 'manufacturerDetail'])->name('manufacturers.detail');
        Route::get('/products', [VendorLibraryWebController::class, 'products'])->name('products');
        Route::get('/products/{id}', [VendorLibraryWebController::class, 'productDetail'])->name('products.detail');
        Route::get('/quirks', [VendorLibraryWebController::class, 'quirks'])->name('quirks');
        Route::get('/templates', [VendorLibraryWebController::class, 'templates'])->name('templates');
    });
});
