<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the auditable trait
     */
    protected static function bootAuditable(): void
    {
        // Log model creation
        static::created(function ($model) {
            if ($model->shouldAudit('created')) {
                $model->auditCreated();
            }
        });

        // Log model updates
        static::updated(function ($model) {
            if ($model->shouldAudit('updated')) {
                $model->auditUpdated();
            }
        });

        // Log model deletion
        static::deleted(function ($model) {
            if ($model->shouldAudit('deleted')) {
                $model->auditDeleted();
            }
        });

        // Log model restoration (if using SoftDeletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if ($model->shouldAudit('restored')) {
                    $model->auditRestored();
                }
            });
        }
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if event should be audited
     */
    protected function shouldAudit(string $event): bool
    {
        // Skip if no user is authenticated and not a console command
        if (!Auth::check() && !app()->runningInConsole()) {
            return false;
        }

        // Check if event is in excluded events
        $excludedEvents = $this->auditExclude ?? [];
        if (in_array($event, $excludedEvents)) {
            return false;
        }

        // Check if only specific events should be audited
        $includedEvents = $this->auditEvents ?? null;
        if ($includedEvents && !in_array($event, $includedEvents)) {
            return false;
        }

        return true;
    }

    /**
     * Log creation event
     */
    protected function auditCreated(): void
    {
        AuditLog::log([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'event' => 'created',
            'action' => class_basename($this) . ' created',
            'description' => $this->getAuditDescription('created'),
            'new_values' => $this->getAuditableAttributes(),
            'category' => $this->getAuditCategory(),
            'severity' => 'info',
        ]);
    }

    /**
     * Log update event
     */
    protected function auditUpdated(): void
    {
        $changes = $this->getChanges();
        $original = $this->getOriginal();
        
        // Get only changed fields from original
        $oldValues = array_intersect_key($original, $changes);
        
        AuditLog::log([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'event' => 'updated',
            'action' => class_basename($this) . ' updated',
            'description' => $this->getAuditDescription('updated'),
            'old_values' => $this->filterSensitiveData($oldValues),
            'new_values' => $this->filterSensitiveData($changes),
            'category' => $this->getAuditCategory(),
            'severity' => 'warning',
        ]);
    }

    /**
     * Log deletion event
     */
    protected function auditDeleted(): void
    {
        AuditLog::log([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'event' => 'deleted',
            'action' => class_basename($this) . ' deleted',
            'description' => $this->getAuditDescription('deleted'),
            'old_values' => $this->getAuditableAttributes(),
            'category' => $this->getAuditCategory(),
            'severity' => 'critical',
        ]);
    }

    /**
     * Log restoration event
     */
    protected function auditRestored(): void
    {
        AuditLog::log([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'event' => 'restored',
            'action' => class_basename($this) . ' restored',
            'description' => $this->getAuditDescription('restored'),
            'new_values' => $this->getAuditableAttributes(),
            'category' => $this->getAuditCategory(),
            'severity' => 'warning',
        ]);
    }

    /**
     * Get auditable attributes (exclude sensitive fields)
     */
    protected function getAuditableAttributes(): array
    {
        return $this->filterSensitiveData($this->getAttributes());
    }

    /**
     * Filter sensitive data from attributes
     */
    protected function filterSensitiveData(array $data): array
    {
        $sensitiveFields = array_merge(
            ['password', 'remember_token', 'api_token', 'secret'],
            $this->auditExcludeFields ?? []
        );
        
        foreach ($sensitiveFields as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }

    /**
     * Get audit description
     */
    protected function getAuditDescription(string $event): string
    {
        $modelName = class_basename($this);
        $identifier = $this->getAuditIdentifier();
        
        return "{$modelName} {$identifier} was {$event}";
    }

    /**
     * Get audit identifier (can be overridden)
     */
    protected function getAuditIdentifier(): string
    {
        if (isset($this->serial_number)) {
            return $this->serial_number;
        }
        
        if (isset($this->email)) {
            return $this->email;
        }
        
        if (isset($this->name)) {
            return $this->name;
        }
        
        return '#' . $this->id;
    }

    /**
     * Get audit category (can be overridden)
     */
    protected function getAuditCategory(): string
    {
        // Use property if defined
        if (isset($this->auditCategory)) {
            return $this->auditCategory;
        }

        // Auto-detect from model name
        $className = class_basename($this);
        
        $categoryMap = [
            'CpeDevice' => 'device',
            'User' => 'user',
            'ConfigurationProfile' => 'configuration',
            'FirmwareVersion' => 'firmware',
            'FirmwareDeployment' => 'firmware',
            'ProvisioningTask' => 'provisioning',
            'Alarm' => 'alarm',
            'Role' => 'rbac',
            'Permission' => 'rbac',
        ];
        
        return $categoryMap[$className] ?? 'general';
    }

    /**
     * Get recent audit logs
     */
    public function recentAuditLogs(int $limit = 10)
    {
        return $this->auditLogs()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
