<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CpeDevice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * EnsureDeviceAccess Middleware
 * 
 * Ensures authenticated users can only access devices they have permission for.
 * Implements multi-tenant device scoping with role-based access control.
 * 
 * Usage:
 *   Route::get('/devices/{device}', ...)->middleware('device.access');
 *   Route::get('/devices/{device}', ...)->middleware('device.access:manager');
 */
class EnsureDeviceAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $minRole Minimum required role (viewer, manager, admin)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $minRole = null): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $user = Auth::user();

        // Super admins bypass device access checks
        if ($user->isSuperAdmin()) {
            Log::info('Device access granted (super-admin)', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return $next($request);
        }

        // Extract device ID from route parameters
        $device = $this->extractDevice($request);

        if (!$device) {
            // No device parameter in route - allow request to continue
            // This is for routes that don't require device scoping
            return $next($request);
        }

        // Check if user has access to this device
        if (!$user->canAccessDevice($device, $minRole)) {
            Log::warning('Device access denied', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'required_role' => $minRole,
                'user_role' => $user->getDeviceRole($device)
            ]);

            return response()->json([
                'success' => false,
                'message' => $minRole 
                    ? "Insufficient permissions. Requires role: {$minRole}" 
                    : 'Access denied to this device'
            ], 403);
        }

        // Log successful access
        Log::info('Device access granted', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'user_role' => $user->getDeviceRole($device),
            'required_role' => $minRole
        ]);

        return $next($request);
    }

    /**
     * Extract device from request route parameters
     * 
     * Supports multiple route parameter names:
     * - {device} - Direct CpeDevice model binding OR numeric ID
     * - {device_id} - Device ID as integer
     * - {id} - When route is under /devices/{id}
     * 
     * SECURITY: Returns null ONLY when route does not contain device parameter.
     * Throws 404 ModelNotFoundException if device ID is invalid.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\CpeDevice|null
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function extractDevice(Request $request): ?CpeDevice
    {
        // Try {device} parameter - can be model instance OR scalar ID
        if ($deviceParam = $request->route('device')) {
            if ($deviceParam instanceof CpeDevice) {
                return $deviceParam;
            }
            
            // SECURITY FIX: Handle scalar ID (int/string) - use findOrFail
            if (is_numeric($deviceParam) || is_string($deviceParam)) {
                return CpeDevice::findOrFail($deviceParam);
            }
        }

        // Try {device_id} parameter - always scalar ID
        if ($deviceId = $request->route('device_id')) {
            return CpeDevice::findOrFail($deviceId);
        }

        // Try {id} parameter (for routes like /devices/{id})
        if ($id = $request->route('id')) {
            // Check if the route is device-related
            $routeName = $request->route()->getName();
            $routePath = $request->route()->uri();
            
            if (str_contains($routePath, 'devices') || str_contains($routeName ?? '', 'device')) {
                return CpeDevice::findOrFail($id);
            }
        }

        // Try device query parameter (fallback for API routes)
        if ($deviceId = $request->query('device_id')) {
            return CpeDevice::findOrFail($deviceId);
        }

        return null;
    }
}
