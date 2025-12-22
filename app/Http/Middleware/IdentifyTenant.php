<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\TenantDiscoveryService;
use App\Contexts\TenantContext;
use App\Models\AuditLog;

class IdentifyTenant
{
    public function __construct(
        protected TenantDiscoveryService $discoveryService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::clear();

        if (!config('tenant.enabled', false)) {
            return $next($request);
        }

        if ($this->discoveryService->isPublicRoute($request)) {
            return $next($request);
        }

        $tenant = $this->discoveryService->resolve($request);

        if (!$tenant && config('tenant.require_tenant', false)) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'Unable to identify tenant for this request.',
            ], 403);
        }

        if ($tenant) {
            TenantContext::set($tenant);
            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('tenant_id', $tenant->id);
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
