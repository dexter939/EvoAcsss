<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class SystemVersion extends Model
{
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getApprovedByUserAttribute()
    {
        if (!$this->approved_by) {
            return null;
        }

        if (is_numeric($this->approved_by)) {
            return User::find($this->approved_by);
        }

        return (object) ['name' => $this->approved_by];
    }
    protected $fillable = [
        'version',
        'deployment_status',
        'environment',
        'deployed_at',
        'completed_at',
        'migrations_run',
        'health_check_results',
        'deployment_notes',
        'error_log',
        'deployed_by',
        'rollback_version',
        'duration_seconds',
        'is_current',
        'github_release_url',
        'github_release_tag',
        'download_path',
        'package_checksum',
        'approval_status',
        'approved_by',
        'approved_at',
        'scheduled_at',
        'changelog',
        'release_notes',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'migrations_run' => 'array',
        'health_check_results' => 'array',
        'is_current' => 'boolean',
    ];

    public static function getCurrentVersion(?string $environment = null): ?self
    {
        $environment = $environment ?? config('app.env', 'production');
        
        return self::where('is_current', true)
            ->where('environment', $environment)
            ->latest('deployed_at')
            ->first();
    }

    public static function recordDeployment(
        string $version,
        string $environment = 'production',
        ?string $deployedBy = null
    ): self {
        return DB::transaction(function () use ($version, $environment, $deployedBy) {
            $previous = self::where('is_current', true)
                ->where('environment', $environment)
                ->first();

            if ($previous) {
                $previous->update(['is_current' => false]);
            }

            return self::create([
                'version' => $version,
                'environment' => $environment,
                'deployment_status' => 'deploying',
                'deployed_at' => now(),
                'deployed_by' => $deployedBy ?? 'system',
                'is_current' => true,
                'rollback_version' => $previous?->version,
            ]);
        });
    }

    public function markAsSuccess(array $healthCheckResults = []): self
    {
        $this->update([
            'deployment_status' => 'success',
            'completed_at' => now(),
            'duration_seconds' => $this->deployed_at ? (int) now()->diffInSeconds($this->deployed_at) : null,
            'health_check_results' => $healthCheckResults,
        ]);

        return $this;
    }

    public function markAsFailed(string $error): self
    {
        return DB::transaction(function () use ($error) {
            $this->update([
                'deployment_status' => 'failed',
                'completed_at' => now(),
                'error_log' => $error,
                'is_current' => false,
            ]);

            if ($this->rollback_version) {
                self::where('version', $this->rollback_version)
                    ->where('environment', $this->environment)
                    ->where('deployment_status', 'success')
                    ->update(['is_current' => true]);
            }

            return $this;
        });
    }

    public function recordMigrations(array $migrations): self
    {
        $this->update([
            'migrations_run' => $migrations,
        ]);

        return $this;
    }

    protected function isHealthy(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->health_check_results) {
                    return null;
                }

                foreach ($this->health_check_results as $check) {
                    if (isset($check['status']) && $check['status'] !== 'ok') {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    protected function durationFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->duration_seconds) {
                    return null;
                }

                $minutes = floor($this->duration_seconds / 60);
                $seconds = $this->duration_seconds % 60;

                return $minutes > 0
                    ? "{$minutes}m {$seconds}s"
                    : "{$seconds}s";
            }
        );
    }

    public function scopeSuccessful($query)
    {
        return $query->where('deployment_status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('deployment_status', 'failed');
    }

    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function approve(?int $userId = null): self
    {
        $this->update([
            'approval_status' => 'approved',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);

        return $this;
    }

    public function reject(?int $userId = null, ?string $reason = null): self
    {
        $this->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
            'deployment_notes' => $reason ? "Rejected: {$reason}" : 'Rejected',
        ]);

        return $this;
    }

    public function scheduleDeployment(\DateTime $scheduledAt): self
    {
        $this->update([
            'approval_status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        return $this;
    }
}
