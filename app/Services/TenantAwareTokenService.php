<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Contexts\TenantContext;
use Laravel\Sanctum\NewAccessToken;
use Illuminate\Support\Facades\DB;

class TenantAwareTokenService
{
    public function createToken(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $token = $user->createToken($name, $abilities, $expiresAt);

        $tenantId = $this->resolveTenantId($user);
        $tenantAbilities = $this->getTenantAbilities($user, $tenantId);

        if ($tenantId) {
            DB::table('personal_access_tokens')
                ->where('id', $token->accessToken->id)
                ->update([
                    'tenant_id' => $tenantId,
                    'tenant_abilities' => json_encode($tenantAbilities),
                ]);
        }

        return $token;
    }

    protected function resolveTenantId(User $user): ?int
    {
        if (TenantContext::check()) {
            return TenantContext::id();
        }

        return $user->tenant_id;
    }

    protected function getTenantAbilities(User $user, ?int $tenantId): array
    {
        if (!$tenantId) {
            return [];
        }

        $abilities = [];

        if ($user->isSuperAdmin()) {
            $abilities[] = 'tenant:admin';
            $abilities[] = 'tenant:manage';
            $abilities[] = 'tenant:view';
        } elseif ($user->hasRole('admin')) {
            $abilities[] = 'tenant:manage';
            $abilities[] = 'tenant:view';
        } else {
            $abilities[] = 'tenant:view';
        }

        return $abilities;
    }

    public function validateTokenTenant(string $tokenId, int $expectedTenantId): bool
    {
        $token = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->first();

        if (!$token) {
            return false;
        }

        if ($token->tenant_id === null) {
            return true;
        }

        return (int) $token->tenant_id === $expectedTenantId;
    }

    public function migrateUserTokens(User $user, int $tenantId): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->whereNull('tenant_id')
            ->update([
                'tenant_id' => $tenantId,
                'tenant_abilities' => json_encode(['tenant:view']),
            ]);
    }

    public function revokeAllTenantTokens(int $tenantId): int
    {
        return DB::table('personal_access_tokens')
            ->where('tenant_id', $tenantId)
            ->delete();
    }
}
