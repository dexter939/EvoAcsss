<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditLog
{
    /**
     * Routes to exclude from audit logging
     */
    protected array $except = [
        'api/metrics',
        'api/health',
        'livewire/*',
        '_debugbar/*',
    ];

    /**
     * HTTP methods to exclude
     */
    protected array $excludedMethods = ['HEAD', 'OPTIONS'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip if should be excluded
        if ($this->shouldExclude($request)) {
            return $response;
        }

        // Record audit log asynchronously (don't slow down response)
        $this->recordAuditLog($request, $response);

        return $response;
    }

    /**
     * Check if request should be excluded
     */
    protected function shouldExclude(Request $request): bool
    {
        // Exclude by HTTP method
        if (in_array($request->method(), $this->excludedMethods)) {
            return true;
        }

        // Exclude by path pattern
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        // Only log API requests and critical web routes
        $path = $request->path();
        if (!str_starts_with($path, 'api/') && 
            !str_starts_with($path, 'acs/devices') &&
            !str_starts_with($path, 'acs/users') &&
            !str_starts_with($path, 'acs/firmware')) {
            return true;
        }

        return false;
    }

    /**
     * Record audit log
     */
    protected function recordAuditLog(Request $request, Response $response): void
    {
        try {
            // Determine severity based on HTTP method and status
            $severity = $this->getSeverity($request, $response);
            
            // Determine category
            $category = $this->getCategory($request);

            // Get action description
            $action = $this->getActionDescription($request);

            AuditLog::create([
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'event' => strtolower($request->method()) . '_request',
                'action' => $action,
                'description' => "{$request->method()} {$request->path()} - Status: {$response->getStatusCode()}",
                'metadata' => [
                    'query_params' => $request->query(),
                    'status_code' => $response->getStatusCode(),
                    'duration_ms' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 2) : null,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'http_method' => $request->method(),
                'category' => $category,
                'severity' => $severity,
                'environment' => config('app.env'),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the application
            \Log::error('Failed to record audit log', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }
    }

    /**
     * Get severity based on request and response
     */
    protected function getSeverity(Request $request, Response $response): string
    {
        $statusCode = $response->getStatusCode();
        
        // Critical for errors and destructive operations
        if ($statusCode >= 500 || in_array($request->method(), ['DELETE'])) {
            return 'critical';
        }

        // Warning for client errors and modifications
        if ($statusCode >= 400 || in_array($request->method(), ['PUT', 'PATCH', 'POST'])) {
            return 'warning';
        }

        // Info for successful reads
        return 'info';
    }

    /**
     * Get category from request path
     */
    protected function getCategory(Request $request): string
    {
        $path = $request->path();

        if (str_contains($path, 'device')) return 'device';
        if (str_contains($path, 'user')) return 'user';
        if (str_contains($path, 'firmware')) return 'firmware';
        if (str_contains($path, 'config')) return 'configuration';
        if (str_contains($path, 'provisioning')) return 'provisioning';
        if (str_contains($path, 'alarm')) return 'alarm';
        if (str_contains($path, 'auth') || str_contains($path, 'login')) return 'authentication';

        return 'general';
    }

    /**
     * Get action description
     */
    protected function getActionDescription(Request $request): string
    {
        $method = $request->method();
        $routeName = $request->route()?->getName() ?? $request->path();

        $actionMap = [
            'GET' => 'Viewed',
            'POST' => 'Created',
            'PUT' => 'Updated',
            'PATCH' => 'Modified',
            'DELETE' => 'Deleted',
        ];

        $action = $actionMap[$method] ?? $method;

        return "{$action} {$routeName}";
    }
}
