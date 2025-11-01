<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * UserDeviceScope - Global Scope for Multi-Tenant Device Filtering
 * 
 * Automatically filters CpeDevice queries to only show devices
 * accessible by the authenticated user, unless user is super-admin.
 * 
 * Security: Prevents users from accessing devices they don't have permission for.
 * Performance: Uses JOIN instead of subquery for better performance.
 * 
 * Usage:
 *   - Applied automatically to CpeDevice model
 *   - Super admins bypass this scope
 *   - Can be disabled with: CpeDevice::withoutGlobalScope(UserDeviceScope::class)
 */
class UserDeviceScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Only apply scope when user is authenticated
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Super admins see all devices
        if ($user->isSuperAdmin()) {
            return;
        }

        // Filter to only devices user has access to
        $builder->whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        });
    }

    /**
     * Extend the query builder with macros.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        /**
         * Add macro to include all devices (bypass scope)
         * 
         * Usage: CpeDevice::withAllDevices()->get()
         */
        $builder->macro('withAllDevices', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        /**
         * Add macro to scope devices by specific user
         * 
         * Usage: CpeDevice::forUser($user)->get()
         */
        $builder->macro('forUser', function (Builder $builder, $user) {
            return $builder->withoutGlobalScope($this)
                ->whereHas('users', function ($query) use ($user) {
                    $userId = is_object($user) ? $user->id : $user;
                    $query->where('users.id', $userId);
                });
        });

        /**
         * Add macro to scope devices by department
         * 
         * Usage: CpeDevice::forDepartment('engineering')->get()
         */
        $builder->macro('forDepartment', function (Builder $builder, string $department) {
            return $builder->withoutGlobalScope($this)
                ->whereHas('users', function ($query) use ($department) {
                    $query->where('user_devices.department', $department);
                });
        });

        /**
         * Add macro to get devices with specific minimum role
         * 
         * Usage: CpeDevice::withMinRole('manager')->get()
         */
        $builder->macro('withMinRole', function (Builder $builder, string $minRole) {
            $user = Auth::user();
            if (!$user || $user->isSuperAdmin()) {
                return $builder->withoutGlobalScope($this);
            }

            // Role hierarchy: admin > manager > viewer
            $roleHierarchy = [
                'viewer' => ['viewer', 'manager', 'admin'],
                'manager' => ['manager', 'admin'],
                'admin' => ['admin']
            ];

            $allowedRoles = $roleHierarchy[$minRole] ?? [];

            return $builder->withoutGlobalScope($this)
                ->whereHas('users', function ($query) use ($user, $allowedRoles) {
                    $query->where('users.id', $user->id)
                        ->whereIn('user_devices.role', $allowedRoles);
                });
        });
    }
}
