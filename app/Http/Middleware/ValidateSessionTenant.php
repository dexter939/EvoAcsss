<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Contexts\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ValidateSessionTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('tenant.enabled', false)) {
            return $next($request);
        }

        if (!config('tenant.enforce_isolation', false)) {
            return $next($request);
        }

        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        $sessionTenantId = session('tenant_id');
        $userTenantId = $user->tenant_id;
        $contextTenantId = TenantContext::id();

        $currentTenantId = $contextTenantId ?? $userTenantId;

        if ($sessionTenantId === null && $currentTenantId !== null) {
            session(['tenant_id' => $currentTenantId]);
            return $next($request);
        }

        if ($sessionTenantId !== null && $currentTenantId !== null) {
            if ((int) $sessionTenantId !== (int) $currentTenantId) {
                $this->logSessionTenantMismatch($request, $user, $sessionTenantId, $currentTenantId);
                
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return redirect()->route('login')->with('error', 'Session expired. Please login again.');
            }
        }

        if ($sessionTenantId === null && $currentTenantId !== null) {
            session(['tenant_id' => $currentTenantId]);
        }

        return $next($request);
    }

    protected function logSessionTenantMismatch(Request $request, $user, int $sessionTenantId, int $currentTenantId): void
    {
        $logData = [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'session_tenant_id' => $sessionTenantId,
            'current_tenant_id' => $currentTenantId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'severity' => 'critical',
        ];

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            Log::channel($channel)->critical('Session tenant mismatch - possible hijacking attempt', $logData);
        } catch (\Throwable $e) {
            Log::critical('Session tenant mismatch - possible hijacking attempt', $logData);
        }
    }
}
