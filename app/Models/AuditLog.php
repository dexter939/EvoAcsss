<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_email',
        'auditable_type',
        'auditable_id',
        'event',
        'action',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'url',
        'route_name',
        'http_method',
        'tags',
        'category',
        'severity',
        'environment',
        'compliance_critical',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'compliance_critical' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the auditable model (polymorphic)
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope: Filter by event type
     */
    public function scopeEvent(Builder $query, string|array $event): Builder
    {
        return is_array($event) 
            ? $query->whereIn('event', $event)
            : $query->where('event', $event);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeCategory(Builder $query, string|array $category): Builder
    {
        return is_array($category)
            ? $query->whereIn('category', $category)
            : $query->where('category', $category);
    }

    /**
     * Scope: Filter by severity
     */
    public function scopeSeverity(Builder $query, string|array $severity): Builder
    {
        return is_array($severity)
            ? $query->whereIn('severity', $severity)
            : $query->where('severity', $severity);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Filter by auditable model
     */
    public function scopeForModel(Builder $query, string $type, ?int $id = null): Builder
    {
        $query->where('auditable_type', $type);
        
        if ($id !== null) {
            $query->where('auditable_id', $id);
        }
        
        return $query;
    }

    /**
     * Scope: Date range filter
     */
    public function scopeDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: Recent logs (last N days)
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Compliance critical logs
     */
    public function scopeComplianceCritical(Builder $query): Builder
    {
        return $query->where('compliance_critical', true);
    }

    /**
     * Scope: Search in description and action
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('action', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('tags', 'like', "%{$term}%");
        });
    }

    /**
     * Get changes summary for display
     */
    public function getChangesSummaryAttribute(): array
    {
        if (empty($this->old_values) || empty($this->new_values)) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $field => $newValue) {
            $oldValue = $this->old_values[$field] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get formatted user info
     */
    public function getUserInfoAttribute(): string
    {
        if ($this->user) {
            return $this->user->name . ' (' . $this->user->email . ')';
        }
        
        return $this->user_email ?? 'System';
    }

    /**
     * Create audit log entry
     */
    public static function log(array $data): self
    {
        // Auto-fill common fields
        $defaults = [
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'route_name' => request()->route()?->getName(),
            'http_method' => request()->method(),
            'environment' => config('app.env'),
        ];

        return self::create(array_merge($defaults, $data));
    }

    /**
     * Log device action
     */
    public static function logDevice(string $event, CpeDevice $device, array $extra = []): self
    {
        return self::log(array_merge([
            'auditable_type' => CpeDevice::class,
            'auditable_id' => $device->id,
            'event' => $event,
            'category' => 'device',
            'action' => ucfirst($event) . ' device: ' . $device->serial_number,
            'description' => "Device {$device->serial_number} ({$device->manufacturer} {$device->model}) was {$event}",
        ], $extra));
    }

    /**
     * Log user action
     */
    public static function logUser(string $event, User $user, array $extra = []): self
    {
        return self::log(array_merge([
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => $event,
            'category' => 'user',
            'action' => ucfirst($event) . ' user: ' . $user->email,
            'compliance_critical' => true, // User changes are always compliance critical
        ], $extra));
    }

    /**
     * Log configuration change
     */
    public static function logConfiguration(string $event, Model $model, array $oldValues, array $newValues, array $extra = []): self
    {
        return self::log(array_merge([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => $event,
            'category' => 'configuration',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => 'warning',
        ], $extra));
    }

    /**
     * Log firmware action
     */
    public static function logFirmware(string $event, $model, array $extra = []): self
    {
        return self::log(array_merge([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => $event,
            'category' => 'firmware',
            'severity' => 'warning',
        ], $extra));
    }

    /**
     * Log authentication event
     */
    public static function logAuth(string $event, ?User $user = null, array $extra = []): self
    {
        return self::log(array_merge([
            'auditable_type' => $user ? User::class : null,
            'auditable_id' => $user?->id,
            'event' => $event,
            'category' => 'authentication',
            'severity' => in_array($event, ['login_failed', 'logout']) ? 'warning' : 'info',
            'compliance_critical' => true,
        ], $extra));
    }
}
