<?php

namespace App\Session;

use App\Contexts\TenantContext;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Session\DatabaseSessionHandler;

class TenantDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected $currentSessionId = null;

    public function __construct(
        ConnectionInterface $connection,
        string $table,
        int $minutes,
        ?string $container = null
    ) {
        parent::__construct($connection, $table, $minutes, $container);
    }

    public function read($sessionId): string|false
    {
        $this->currentSessionId = $sessionId;
        
        $session = $this->getQuery()
            ->where('id', $sessionId)
            ->first();

        if ($session === null) {
            return '';
        }

        if ($this->expired($session)) {
            return '';
        }

        if (config('tenant.enabled', false) && config('tenant.enforce_isolation', false)) {
            $tenantId = $this->resolveTenantIdForValidation();
            
            if ($tenantId !== null && $session->tenant_id !== null) {
                if ((int) $session->tenant_id !== (int) $tenantId) {
                    $this->logCrossTenantSessionAccess($sessionId, $session->tenant_id, $tenantId);
                    return '';
                }
            }
            
            if (config('tenant.require_session_tenant', false)) {
                if ($tenantId !== null && $session->tenant_id === null) {
                    $this->logNullTenantSessionRejected($sessionId, $tenantId);
                    return '';
                }
            }
        }

        if (isset($session->payload)) {
            return base64_decode($session->payload);
        }

        return '';
    }

    public function write($sessionId, $data): bool
    {
        $this->currentSessionId = $sessionId;
        $payload = $this->getDefaultPayload($data);

        if (config('tenant.enabled', false)) {
            $payload['tenant_id'] = $this->resolveTenantIdForWrite();
        }

        if ($this->sessionExists($sessionId)) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return true;
    }

    protected function resolveTenantIdForValidation(): ?int
    {
        if (TenantContext::check()) {
            return TenantContext::id();
        }

        if ($this->container && $this->container->bound(Guard::class)) {
            try {
                $user = $this->container->make(Guard::class)->user();
                if ($user && isset($user->tenant_id)) {
                    return $user->tenant_id;
                }
            } catch (\Throwable $e) {
                // Guard not ready yet
            }
        }

        return null;
    }

    protected function resolveTenantIdForWrite(): ?int
    {
        if (TenantContext::check()) {
            return TenantContext::id();
        }

        if ($this->container && $this->container->bound(Guard::class)) {
            try {
                $user = $this->container->make(Guard::class)->user();
                if ($user && isset($user->tenant_id)) {
                    return $user->tenant_id;
                }
            } catch (\Throwable $e) {
                // Guard not ready yet
            }
        }

        return null;
    }

    protected function sessionExists(string $sessionId): bool
    {
        return $this->getQuery()->where('id', $sessionId)->exists();
    }

    protected function performInsert($sessionId, $payload): bool
    {
        try {
            return $this->getQuery()->insert(array_merge(['id' => $sessionId], $payload));
        } catch (\Illuminate\Database\QueryException $e) {
            $this->performUpdate($sessionId, $payload);
            return true;
        }
    }

    protected function performUpdate($sessionId, $payload): int
    {
        return $this->getQuery()->where('id', $sessionId)->update($payload);
    }

    protected function getDefaultPayload($data): array
    {
        $payload = [
            'payload' => base64_encode($data),
            'last_activity' => $this->currentTime(),
        ];

        if ($this->container && $this->container->bound(Guard::class)) {
            try {
                $user = $this->container->make(Guard::class)->user();
                if ($user) {
                    $payload['user_id'] = $user->getAuthIdentifier();
                }
            } catch (\Throwable $e) {
                // Guard not ready
            }
        }

        if (request()) {
            $payload['ip_address'] = request()->ip();
            $payload['user_agent'] = substr((string) request()->userAgent(), 0, 500);
        }

        return $payload;
    }

    protected function logCrossTenantSessionAccess(string $sessionId, ?int $sessionTenantId, int $contextTenantId): void
    {
        $logData = [
            'session_id' => substr($sessionId, 0, 8) . '...',
            'session_tenant_id' => $sessionTenantId,
            'context_tenant_id' => $contextTenantId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'severity' => 'critical',
        ];

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            \Illuminate\Support\Facades\Log::channel($channel)->warning('Cross-tenant session access attempt', $logData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cross-tenant session access attempt', $logData);
        }
    }

    protected function logNullTenantSessionRejected(string $sessionId, int $contextTenantId): void
    {
        $logData = [
            'session_id' => substr($sessionId, 0, 8) . '...',
            'context_tenant_id' => $contextTenantId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'severity' => 'warning',
        ];

        try {
            $channel = config('logging.channels.security') ? 'security' : 'daily';
            \Illuminate\Support\Facades\Log::channel($channel)->warning('Null-tenant session rejected (require_session_tenant enabled)', $logData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Null-tenant session rejected (require_session_tenant enabled)', $logData);
        }
    }
}
