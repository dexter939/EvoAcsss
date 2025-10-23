<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log an audit event
     */
    public function log(string $event, array $data = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'event' => $event,
        ], $data));
    }

    /**
     * Log model creation
     */
    public function created(Model $model, array $extra = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => 'created',
            'action' => class_basename($model) . ' created',
            'description' => $this->getModelDescription($model, 'created'),
            'new_values' => $this->getModelAttributes($model),
            'category' => $this->getCategoryFromModel($model),
            'severity' => 'info',
        ], $extra));
    }

    /**
     * Log model update
     */
    public function updated(Model $model, array $oldValues = [], array $extra = []): AuditLog
    {
        $changes = $model->getChanges();
        
        return AuditLog::log(array_merge([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => 'updated',
            'action' => class_basename($model) . ' updated',
            'description' => $this->getModelDescription($model, 'updated'),
            'old_values' => $oldValues,
            'new_values' => $changes,
            'category' => $this->getCategoryFromModel($model),
            'severity' => 'warning',
        ], $extra));
    }

    /**
     * Log model deletion
     */
    public function deleted(Model $model, array $extra = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => 'deleted',
            'action' => class_basename($model) . ' deleted',
            'description' => $this->getModelDescription($model, 'deleted'),
            'old_values' => $this->getModelAttributes($model),
            'category' => $this->getCategoryFromModel($model),
            'severity' => 'critical',
        ], $extra));
    }

    /**
     * Log bulk action
     */
    public function bulk(string $action, string $modelClass, int $count, array $extra = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'event' => 'bulk_' . $action,
            'action' => "Bulk {$action} on " . class_basename($modelClass),
            'description' => "Performed bulk {$action} on {$count} " . class_basename($modelClass) . "(s)",
            'metadata' => ['count' => $count, 'model' => $modelClass],
            'category' => $this->getCategoryFromModel($modelClass),
            'severity' => 'warning',
        ], $extra));
    }

    /**
     * Log login event
     */
    public function login($user): AuditLog
    {
        return AuditLog::logAuth('login', $user, [
            'action' => 'User logged in',
            'description' => "User {$user->email} logged in successfully",
        ]);
    }

    /**
     * Log failed login
     */
    public function loginFailed(string $email, string $reason = 'Invalid credentials'): AuditLog
    {
        return AuditLog::logAuth('login_failed', null, [
            'user_email' => $email,
            'action' => 'Login failed',
            'description' => "Failed login attempt for {$email}: {$reason}",
            'severity' => 'warning',
        ]);
    }

    /**
     * Log logout
     */
    public function logout($user): AuditLog
    {
        return AuditLog::logAuth('logout', $user, [
            'action' => 'User logged out',
            'description' => "User {$user->email} logged out",
        ]);
    }

    /**
     * Log password change
     */
    public function passwordChanged($user): AuditLog
    {
        return AuditLog::logUser('password_changed', $user, [
            'action' => 'Password changed',
            'description' => "User {$user->email} changed their password",
            'severity' => 'warning',
            'compliance_critical' => true,
        ]);
    }

    /**
     * Log permission change
     */
    public function permissionChanged($user, string $permission, string $action): AuditLog
    {
        return AuditLog::logUser('permission_changed', $user, [
            'action' => "Permission {$action}",
            'description' => "Permission '{$permission}' was {$action} for user {$user->email}",
            'metadata' => ['permission' => $permission, 'action' => $action],
            'severity' => 'critical',
            'compliance_critical' => true,
        ]);
    }

    /**
     * Log API access
     */
    public function apiAccess(string $endpoint, string $method, int $statusCode, array $extra = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'event' => 'api_access',
            'action' => "API {$method} {$endpoint}",
            'description' => "API request: {$method} {$endpoint} - Status: {$statusCode}",
            'category' => 'api',
            'severity' => $statusCode >= 400 ? 'warning' : 'info',
            'metadata' => ['status_code' => $statusCode],
        ], $extra));
    }

    /**
     * Log configuration change
     */
    public function configurationChanged(Model $model, array $oldValues, array $newValues): AuditLog
    {
        return AuditLog::logConfiguration('configuration_changed', $model, $oldValues, $newValues, [
            'action' => 'Configuration updated',
            'description' => $this->getConfigurationChangeDescription($model, $oldValues, $newValues),
        ]);
    }

    /**
     * Log firmware deployment
     */
    public function firmwareDeployed($deployment): AuditLog
    {
        return AuditLog::logFirmware('firmware_deployed', $deployment, [
            'action' => 'Firmware deployment started',
            'description' => "Firmware deployment initiated",
            'severity' => 'warning',
        ]);
    }

    /**
     * Log security event
     */
    public function securityEvent(string $event, string $description, array $extra = []): AuditLog
    {
        return AuditLog::log(array_merge([
            'event' => $event,
            'action' => 'Security event: ' . $event,
            'description' => $description,
            'category' => 'security',
            'severity' => 'critical',
            'compliance_critical' => true,
        ], $extra));
    }

    /**
     * Get model attributes for audit
     */
    private function getModelAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'remember_token', 'api_token'];
        foreach ($sensitiveFields as $field) {
            unset($attributes[$field]);
        }
        
        return $attributes;
    }

    /**
     * Get model description for audit
     */
    private function getModelDescription(Model $model, string $action): string
    {
        $modelName = class_basename($model);
        $identifier = $this->getModelIdentifier($model);
        
        return "{$modelName} {$identifier} was {$action}";
    }

    /**
     * Get model identifier (serial_number, email, name, or id)
     */
    private function getModelIdentifier(Model $model): string
    {
        if (isset($model->serial_number)) {
            return $model->serial_number;
        }
        
        if (isset($model->email)) {
            return $model->email;
        }
        
        if (isset($model->name)) {
            return $model->name;
        }
        
        return '#' . $model->id;
    }

    /**
     * Get category from model class
     */
    private function getCategoryFromModel($model): string
    {
        $modelClass = is_string($model) ? $model : get_class($model);
        $className = class_basename($modelClass);
        
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
     * Get configuration change description
     */
    private function getConfigurationChangeDescription(Model $model, array $oldValues, array $newValues): string
    {
        $changes = [];
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? 'null';
            if ($oldValue !== $newValue) {
                $changes[] = "{$field}: {$oldValue} → {$newValue}";
            }
        }
        
        $changesText = implode(', ', array_slice($changes, 0, 3));
        if (count($changes) > 3) {
            $changesText .= ' and ' . (count($changes) - 3) . ' more';
        }
        
        return "Configuration changed: {$changesText}";
    }
}
