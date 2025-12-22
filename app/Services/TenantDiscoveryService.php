<?php

namespace App\Services;

use App\Models\Tenant;
use App\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantDiscoveryService
{
    protected function getPublicRoutes(): array
    {
        return config('tenant.public_routes', [
            'health',
            'up',
            'metrics',
            'tr069',
            'tr069/*',
            'usp',
        ]);
    }

    public function resolve(Request $request): ?Tenant
    {
        $tenant = $this->resolveFromSubdomain($request)
            ?? $this->resolveFromHeader($request)
            ?? $this->resolveFromToken($request)
            ?? $this->resolveFromUser($request)
            ?? $this->resolveDefault();

        return $tenant;
    }

    protected function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $baseDomain = config('app.domain', 'localhost');
        
        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            
            if ($subdomain && $subdomain !== 'www') {
                return $this->findTenantBySubdomain($subdomain);
            }
        }

        return null;
    }

    protected function resolveFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header(config('tenant.header_name', 'X-Tenant-ID'));
        
        if ($tenantId) {
            if (is_numeric($tenantId)) {
                return $this->findTenantById((int) $tenantId);
            }
            return $this->findTenantBySlug($tenantId);
        }

        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $this->findTenantByApiKey($apiKey);
        }

        return null;
    }

    protected function resolveFromToken(Request $request): ?Tenant
    {
        $user = $request->user();
        
        if ($user && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();
            
            if (isset($token->tenant_id) && $token->tenant_id) {
                return $this->findTenantById($token->tenant_id);
            }
        }

        return null;
    }

    protected function resolveFromUser(Request $request): ?Tenant
    {
        $user = $request->user();
        
        if ($user && $user->tenant_id) {
            return $this->findTenantById($user->tenant_id);
        }

        return null;
    }

    protected function resolveDefault(): ?Tenant
    {
        if (!config('tenant.require_tenant', false)) {
            $defaultId = config('tenant.default_id', 1);
            return $this->findTenantById($defaultId);
        }

        return null;
    }

    protected function findTenantById(int $id): ?Tenant
    {
        return Cache::remember("tenant:id:{$id}", 300, function () use ($id) {
            return Tenant::where('id', $id)->where('is_active', true)->first();
        });
    }

    protected function findTenantBySlug(string $slug): ?Tenant
    {
        return Cache::remember("tenant:slug:{$slug}", 300, function () use ($slug) {
            return Tenant::findBySlug($slug);
        });
    }

    protected function findTenantBySubdomain(string $subdomain): ?Tenant
    {
        return Cache::remember("tenant:subdomain:{$subdomain}", 300, function () use ($subdomain) {
            return Tenant::findBySubdomain($subdomain);
        });
    }

    protected function findTenantByApiKey(string $apiKey): ?Tenant
    {
        $cacheKey = "tenant:apikey:" . hash('sha256', $apiKey);
        
        return Cache::remember($cacheKey, 300, function () use ($apiKey) {
            return Tenant::findByApiKey($apiKey);
        });
    }

    public function isPublicRoute(Request $request): bool
    {
        $path = $request->path();
        $publicRoutes = $this->getPublicRoutes();
        
        foreach ($publicRoutes as $route) {
            if ($route === $path) {
                return true;
            }
            
            if (str_ends_with($route, '/*')) {
                $prefix = rtrim($route, '/*');
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clearTenantCache(Tenant $tenant): void
    {
        Cache::forget("tenant:id:{$tenant->id}");
        Cache::forget("tenant:slug:{$tenant->slug}");
        
        if ($tenant->subdomain) {
            Cache::forget("tenant:subdomain:{$tenant->subdomain}");
        }
        
        if ($tenant->api_key) {
            Cache::forget("tenant:apikey:" . hash('sha256', $tenant->api_key));
        }
    }
}
