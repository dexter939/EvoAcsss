<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CpeDevice;

/**
 * CpeDevicePolicy - Authorization Policy for Device Access Management
 * 
 * Implements fine-grained access control for device operations.
 * Super admins bypass all checks.
 */
class CpeDevicePolicy
{
    /**
     * Determine if user can view any devices
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can view specific device
     */
    public function view(User $user, CpeDevice $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $user->canAccessDevice($device);
    }

    /**
     * Determine if user can update device
     */
    public function update(User $user, CpeDevice $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $user->canAccessDevice($device, 'manager');
    }

    /**
     * Determine if user can delete device
     */
    public function delete(User $user, CpeDevice $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $user->canAccessDevice($device, 'admin');
    }

    /**
     * Determine if user can provision device
     */
    public function provision(User $user, CpeDevice $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $user->canAccessDevice($device, 'manager');
    }

    /**
     * Determine if user can manage access permissions
     */
    public function manageAccess(User $user, CpeDevice $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return $user->canAccessDevice($device, 'admin');
    }
}
