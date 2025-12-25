<?php

namespace App\Jobs\Middleware;

use App\Contexts\TenantContext;
use App\Models\Tenant;
use Closure;

class RestoreTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        $tenantId = $this->resolveTenantId($job);

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                TenantContext::set($tenant);
            }
        }

        try {
            $next($job);
        } finally {
            TenantContext::clear();
        }
    }

    protected function resolveTenantId(object $job): ?int
    {
        if (property_exists($job, 'tenantId') && $job->tenantId !== null) {
            return $job->tenantId;
        }

        if (method_exists($job, 'getTenantId')) {
            $tenantId = $job->getTenantId();
            if ($tenantId !== null) {
                return $tenantId;
            }
        }

        if (property_exists($job, 'task') && $job->task?->cpeDevice?->tenant_id) {
            return $job->task->cpeDevice->tenant_id;
        }

        if (property_exists($job, 'deployment') && $job->deployment?->cpeDevice?->tenant_id) {
            return $job->deployment->cpeDevice->tenant_id;
        }

        if (property_exists($job, 'device') && $job->device?->tenant_id) {
            return $job->device->tenant_id;
        }

        if (property_exists($job, 'storageService') && $job->storageService?->cpeDevice?->tenant_id) {
            return $job->storageService->cpeDevice->tenant_id;
        }

        if (property_exists($job, 'voiceService') && $job->voiceService?->cpeDevice?->tenant_id) {
            return $job->voiceService->cpeDevice->tenant_id;
        }

        return null;
    }
}
