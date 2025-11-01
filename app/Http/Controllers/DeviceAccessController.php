<?php

namespace App\Http\Controllers;

use App\Models\CpeDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsAuditActivity;

/**
 * DeviceAccessController - Multi-Tenant Device Permission Management
 * 
 * Manages user-to-device access relationships with role-based permissions.
 * Implements carrier-grade access control for 100K+ devices.
 * 
 * Features:
 * - Assign/revoke user access to devices
 * - Role hierarchy enforcement (viewer < manager < admin)
 * - Department-based grouping
 * - Comprehensive audit logging
 * - Bulk operations support
 */
class DeviceAccessController extends Controller
{
    use LogsAuditActivity;

    /**
     * Display device access management page
     * 
     * @param int $deviceId Device ID
     * @return \Illuminate\View\View
     */
    public function index($deviceId)
    {
        // Use withAllDevices to bypass global scope for super-admin
        $device = CpeDevice::withAllDevices()->findOrFail($deviceId);

        // Check if current user can manage device permissions
        $this->authorize('manage-access', $device);

        $users = $device->users()
            ->withPivot('role', 'department')
            ->withTimestamps()
            ->get();

        $allUsers = User::whereDoesntHave('devices', function ($query) use ($deviceId) {
            $query->where('cpe_device_id', $deviceId);
        })->get();

        return view('acs.devices.access-management', compact('device', 'users', 'allUsers'));
    }

    /**
     * Grant user access to device
     * 
     * @param Request $request
     * @param int $deviceId Device ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function grantAccess(Request $request, $deviceId)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:viewer,manager,admin',
            'department' => 'nullable|string|max:100'
        ]);

        try {
            DB::beginTransaction();

            // Use withAllDevices to bypass global scope
            $device = CpeDevice::withAllDevices()->findOrFail($deviceId);
            $this->authorize('manage-access', $device);

            $user = User::findOrFail($validated['user_id']);

            // Check if user already has access
            if ($device->hasUserAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has access to this device'
                ], 400);
            }

            // Grant access
            $device->users()->attach($user->id, [
                'role' => $validated['role'],
                'department' => $validated['department'] ?? null
            ]);

            // Audit log
            $this->logAuditActivity(
                'device_access_granted',
                'device',
                $device->id,
                "Granted {$validated['role']} access to device {$device->serial_number} for user {$user->name}",
                [
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'role' => $validated['role'],
                    'department' => $validated['department'] ?? null,
                    'granted_by' => Auth::user()->name
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Access granted successfully',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email']),
                    'role' => $validated['role'],
                    'department' => $validated['department'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to grant device access', [
                'device_id' => $deviceId,
                'user_id' => $validated['user_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to grant access: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke user access to device
     * 
     * @param Request $request
     * @param int $deviceId Device ID
     * @param int $userId User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeAccess(Request $request, $deviceId, $userId)
    {
        try {
            DB::beginTransaction();

            // Use withAllDevices to bypass global scope
            $device = CpeDevice::withAllDevices()->findOrFail($deviceId);
            $this->authorize('manage-access', $device);

            $user = User::findOrFail($userId);

            // Check if user has access
            if (!$device->hasUserAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have access to this device'
                ], 400);
            }

            // Get current role before revoking (for audit log)
            $currentAccess = $device->users()
                ->where('users.id', $userId)
                ->first();
            $currentRole = $currentAccess?->pivot->role;
            $currentDepartment = $currentAccess?->pivot->department;

            // Revoke access
            $device->users()->detach($userId);

            // Audit log
            $this->logAuditActivity(
                'device_access_revoked',
                'device',
                $device->id,
                "Revoked {$currentRole} access to device {$device->serial_number} for user {$user->name}",
                [
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'previous_role' => $currentRole,
                    'previous_department' => $currentDepartment,
                    'revoked_by' => Auth::user()->name
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Access revoked successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revoke device access', [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke access: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's role for device
     * 
     * @param Request $request
     * @param int $deviceId Device ID
     * @param int $userId User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRole(Request $request, $deviceId, $userId)
    {
        $validated = $request->validate([
            'role' => 'required|in:viewer,manager,admin',
            'department' => 'nullable|string|max:100'
        ]);

        try {
            DB::beginTransaction();

            // Use withAllDevices to bypass global scope
            $device = CpeDevice::withAllDevices()->findOrFail($deviceId);
            $this->authorize('manage-access', $device);

            $user = User::findOrFail($userId);

            // Check if user has access
            if (!$device->hasUserAccess($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have access to this device'
                ], 400);
            }

            // Get current role
            $currentAccess = $device->users()
                ->where('users.id', $userId)
                ->first();
            $previousRole = $currentAccess?->pivot->role;
            $previousDepartment = $currentAccess?->pivot->department;

            // Update role and department
            $device->users()->updateExistingPivot($userId, [
                'role' => $validated['role'],
                'department' => $validated['department'] ?? $previousDepartment
            ]);

            // Audit log
            $this->logAuditActivity(
                'device_access_updated',
                'device',
                $device->id,
                "Updated access role for device {$device->serial_number} and user {$user->name} from {$previousRole} to {$validated['role']}",
                [
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'previous_role' => $previousRole,
                    'new_role' => $validated['role'],
                    'previous_department' => $previousDepartment,
                    'new_department' => $validated['department'] ?? $previousDepartment,
                    'updated_by' => Auth::user()->name
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => [
                    'previous_role' => $previousRole,
                    'new_role' => $validated['role']
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update device access role', [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk grant access to multiple users
     * 
     * @param Request $request
     * @param int $deviceId Device ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkGrantAccess(Request $request, $deviceId)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'role' => 'required|in:viewer,manager,admin',
            'department' => 'nullable|string|max:100'
        ]);

        try {
            DB::beginTransaction();

            // Use withAllDevices to bypass global scope
            $device = CpeDevice::withAllDevices()->findOrFail($deviceId);
            $this->authorize('manage-access', $device);

            $granted = 0;
            $skipped = 0;
            $errors = [];

            foreach ($validated['user_ids'] as $userId) {
                $user = User::find($userId);
                
                if (!$user) {
                    $errors[] = "User ID {$userId} not found";
                    continue;
                }

                // Skip if already has access
                if ($device->hasUserAccess($user)) {
                    $skipped++;
                    continue;
                }

                // Grant access
                $device->users()->attach($userId, [
                    'role' => $validated['role'],
                    'department' => $validated['department'] ?? null
                ]);

                $granted++;
            }

            // Audit log
            $this->logAuditActivity(
                'device_access_bulk_granted',
                'device',
                $device->id,
                "Bulk granted {$validated['role']} access to device {$device->serial_number} for {$granted} users",
                [
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                    'user_ids' => $validated['user_ids'],
                    'role' => $validated['role'],
                    'department' => $validated['department'] ?? null,
                    'granted_count' => $granted,
                    'skipped_count' => $skipped,
                    'errors' => $errors,
                    'granted_by' => Auth::user()->name
                ]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk access grant completed: {$granted} granted, {$skipped} skipped",
                'data' => [
                    'granted' => $granted,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk grant device access', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk grant access: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of users with access to device
     * 
     * @param int $deviceId Device ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersWithAccess($deviceId)
    {
        try {
            // Use withAllDevices to bypass global scope
            $device = CpeDevice::withAllDevices()->findOrFail($deviceId);
            
            $users = $device->users()
                ->withPivot('role', 'department', 'created_at')
                ->orderBy('user_devices.role', 'desc') // admin first
                ->orderBy('users.name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->pivot->role,
                        'department' => $user->pivot->department,
                        'granted_at' => $user->pivot->created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }
}
