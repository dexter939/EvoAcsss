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
