<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCredential extends Model
{
    protected $fillable = [
        'tenant_id',
        'credential_type',
        'credential_key',
        'credential_secret',
        'scopes',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'credential_secret',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('credential_type', $type);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public static function findByKey(string $key): ?self
    {
        return static::where('credential_key', $key)
            ->active()
            ->notExpired()
            ->first();
    }
}
