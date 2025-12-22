<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasTenant;

class Alarm extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'tenant_id',
        'device_id',
        'alarm_type',
        'severity',
        'status',
        'category',
        'title',
        'description',
        'metadata',
        'raised_at',
        'acknowledged_at',
        'acknowledged_by',
        'cleared_at',
        'resolution',
    ];

    protected $casts = [
        'metadata' => 'array',
        'raised_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(CpeDevice::class, 'device_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAcknowledged(Builder $query): Builder
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeCleared(Builder $query): Builder
    {
        return $query->where('status', 'cleared');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeMajor(Builder $query): Builder
    {
        return $query->where('severity', 'major');
    }

    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'critical' => 'danger',
            'major' => 'warning',
            'minor' => 'info',
            'warning' => 'secondary',
            'info' => 'primary',
            default => 'dark',
        };
    }

    public function getSeverityBadgeAttribute(): string
    {
        $color = $this->severity_color;
        return "<span class='badge bg-gradient-{$color}'>{$this->severity}</span>";
    }

    public function getStatusBadgeAttribute(): string
    {
        $color = match($this->status) {
            'active' => 'danger',
            'acknowledged' => 'warning',
            'cleared' => 'success',
            default => 'secondary',
        };
        return "<span class='badge bg-{$color}'>{$this->status}</span>";
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCleared(): bool
    {
        return $this->status === 'cleared';
    }
}
