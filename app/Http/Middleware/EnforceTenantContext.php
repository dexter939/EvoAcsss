<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Contexts\TenantContext;
use App\Models\AuditLog;

class EnforceTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('tenant.enabled', false)) {
            return $next($request);
        }

        if (!config('tenant.enforce_isolation', false)) {
            return $next($request);
        }

        $tenant = TenantContext::get();
        $user = $request->user();

        if ($user && $tenant && $user->tenant_id !== $tenant->id) {
            if (class_exists(AuditLog::class)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'cross_tenant_access_attempt',
                    'resource_type' => 'security',
                    'description' => 'Cross-tenant access attempt detected',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'user_tenant_id' => $user->tenant_id,
                        'request_tenant_id' => $tenant->id,
                        'path' => $request->path(),
                        'severity' => 'critical',
                    ],
                ]);
            }

            return response()->json([
                'error' => 'Access denied',
                'message' => 'Cross-tenant access is not allowed.',
            ], 403);
        }

        return $next($request);
    }
}
