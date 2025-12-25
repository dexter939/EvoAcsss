<?php

use App\Models\CpeDevice;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * User-specific alarms channel (tenant-scoped)
 * SECURITY: Only the specific user can subscribe to their own channel
 * Alarms are broadcast ONLY to users with explicit device access via user_devices pivot
 * NOTE: Alarms without device association are not broadcast (logged only)
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Device-specific channel with multi-tenant access control
 * Users can only subscribe to devices they have access to
 */
Broadcast::channel('device.{deviceSerial}', function ($user, $deviceSerial) {
    // Super admins can access all devices
    if ($user->isSuperAdmin()) {
        return true;
    }
    
    // Check if user has access to this specific device
    $device = CpeDevice::where('serial_number', $deviceSerial)->first();
    
    if (!$device) {
        return false;
    }
    
    return $user->canAccessDevice($device);
});

/**
 * Department-specific channel for device updates
 */
Broadcast::channel('department.{department}', function ($user, $department) {
    return $user->department === $department || $user->isSuperAdmin();
});

/**
 * Tenant-specific alarms channel (multi-tenant broadcasting)
 * SECURITY: Users can only subscribe to their own tenant's channel
 * All users within a tenant receive broadcasts on this channel
 */
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    if ($user->isSuperAdmin()) {
        return true;
    }
    
    return $user->tenant_id !== null && (int) $user->tenant_id === (int) $tenantId;
});

/**
 * Tenant-specific alarms presence channel
 * Shows which users from the tenant are online
 */
Broadcast::channel('tenant.{tenantId}.presence', function ($user, $tenantId) {
    if ($user->tenant_id !== null && (int) $user->tenant_id === (int) $tenantId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
    
    return false;
});

/**
 * Tenant-specific alarms channel by severity
 * Allows filtering broadcasts by alarm severity level
 */
Broadcast::channel('tenant.{tenantId}.alarms.{severity}', function ($user, $tenantId, $severity) {
    $validSeverities = ['critical', 'major', 'minor', 'warning', 'info'];
    
    if (!in_array($severity, $validSeverities)) {
        return false;
    }
    
    if ($user->isSuperAdmin()) {
        return true;
    }
    
    return $user->tenant_id !== null && (int) $user->tenant_id === (int) $tenantId;
});
