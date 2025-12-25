<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Contexts\TenantContext;
use App\Services\TenantAwareTokenService;
use App\Services\TenantAnomalyDetector;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class ValidateTokenTenant
{
    public function __construct(
        protected TenantAwareTokenService $tokenService,
        protected TenantAnomalyDetector $anomalyDetector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        
        if (!$token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $this->anomalyDetector->checkTokenAnomaly($token, $request);

        $tokenTenantId = $this->getTokenTenantId($token->id);
        $contextTenantId = TenantContext::id();

        // Enforce token-context tenant match
        if ((int) $tokenTenantId !== (int) $contextTenantId) {
            $this->logSecurityEvent(
                'cross_tenant_token_access',
                'Cross-tenant token access attempt detected',
                $request,
                $user,
                [
                    'token_tenant_id' => $tokenTenantId,
                    'context_tenant_id' => $contextTenantId,
                    'user_tenant_id' => $user->tenant_id,
                    'severity' => 'critical',
                ]
            );

            return response()->json([
                'error' => 'Token scope violation',
                'message' => 'This token is not valid for the requested tenant.',
            ], 403);
        }

        // Enforce token-user tenant match
        if ((int) $tokenTenantId !== (int) $user->tenant_id) {
            $this->logSecurityEvent(
                'token_user_tenant_mismatch',
                'Token tenant does not match user tenant',
                $request,
                $user,
                [
                    'token_tenant_id' => $tokenTenantId,
                    'user_tenant_id' => $user->tenant_id,
                    'severity' => 'warning',
                ]
            );

            return response()->json([
                'error' => 'Token-user mismatch',
                'message' => 'Token tenant does not match user tenant.',
            ], 403);
        }

        return $next($request);
    }

    protected function getTokenTenantId(int $tokenId): ?int
    {
        $token = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->first(['tenant_id']);

        return $token?->tenant_id;
    }

    protected function logSecurityEvent(
        string $action,
        string $description,
        Request $request,
        $user,
        array $metadata
    ): void {
        $logData = [
            'user_id' => $user->id,
            'action' => $action,
            'resource_type' => 'security',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'metadata' => $metadata,
        ];

        try {
            if (class_exists(AuditLog::class)) {
                AuditLog::create($logData);
            }
        } catch (\Throwable $e) {
            // Fallback handled below
        }

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            Log::channel($channel)->warning("SECURITY: {$action}", $logData);
        } catch (\Throwable $e) {
            Log::warning("SECURITY: {$action}", $logData);
        }
    }
}
