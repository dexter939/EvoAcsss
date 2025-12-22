<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Contexts\TenantContext;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasTenant
{
    public static function bootHasTenant(): void
    {
        if (config('tenant.enabled', false)) {
            static::addGlobalScope(new TenantScope());
        }

        static::creating(function ($model) {
            if (config('tenant.enabled', false) && empty($model->tenant_id)) {
                $model->tenant_id = static::resolveTenantIdForModel();
            }
        });
    }

    protected static function resolveTenantIdForModel(): ?int
    {
        if (TenantContext::check()) {
            return TenantContext::id();
        }

        if (auth()->check() && auth()->user()?->tenant_id) {
            return auth()->user()->tenant_id;
        }

        return null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable() . '.tenant_id', $tenantId);
    }

    public function scopeAllTenants($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    public function belongsToTenant(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return true;
        }

        return $this->tenant_id === $tenantId;
    }

    public function assignToTenant(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $this->update(['tenant_id' => $tenantId]);
    }
}
