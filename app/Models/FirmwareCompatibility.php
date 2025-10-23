<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirmwareCompatibility extends Model
{
    protected $table = 'firmware_compatibility_matrix';

    protected $fillable = [
        'firmware_version_id',
        'product_id',
        'compatibility_status',
        'min_hardware_revision',
        'max_hardware_revision',
        'supported_features',
        'known_issues',
        'prerequisites',
        'tested',
        'last_tested_at',
        'tested_by',
        'installation_notes',
        'rollback_notes',
        'performance_rating',
        'stability_rating'
    ];

    protected $casts = [
        'supported_features' => 'array',
        'known_issues' => 'array',
        'prerequisites' => 'array',
        'tested' => 'boolean',
        'last_tested_at' => 'datetime',
        'performance_rating' => 'integer',
        'stability_rating' => 'integer'
    ];

    public function firmwareVersion(): BelongsTo
    {
        return $this->belongsTo(FirmwareVersion::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RouterProduct::class, 'product_id');
    }

    public function getCompatibilityBadgeClass(): string
    {
        return match($this->compatibility_status) {
            'compatible' => 'bg-gradient-success',
            'compatible_with_issues' => 'bg-gradient-warning',
            'incompatible' => 'bg-gradient-danger',
            'untested' => 'bg-gradient-secondary',
            'beta' => 'bg-gradient-info',
            default => 'bg-gradient-light'
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->compatibility_status) {
            'compatible' => 'Compatible',
            'compatible_with_issues' => 'Compatible (Issues)',
            'incompatible' => 'Incompatible',
            'untested' => 'Untested',
            'beta' => 'Beta Support',
            default => 'Unknown'
        };
    }

    public function isFullyTested(): bool
    {
        return $this->tested && 
               $this->performance_rating !== null && 
               $this->stability_rating !== null;
    }
}
