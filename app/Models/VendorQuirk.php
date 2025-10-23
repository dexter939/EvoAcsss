<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorQuirk extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'product_id',
        'quirk_type',
        'quirk_name',
        'description',
        'affects_protocol',
        'firmware_versions_affected',
        'workaround_config',
        'workaround_notes',
        'severity',
        'auto_apply',
        'discovered_by',
        'discovered_at',
        'is_active'
    ];

    protected $casts = [
        'workaround_config' => 'array',
        'auto_apply' => 'boolean',
        'is_active' => 'boolean',
        'discovered_at' => 'datetime'
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(RouterManufacturer::class, 'manufacturer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RouterProduct::class, 'product_id');
    }

    public function getSeverityBadgeClass(): string
    {
        return match($this->severity) {
            'critical' => 'bg-gradient-danger',
            'high' => 'bg-gradient-warning',
            'medium' => 'bg-gradient-info',
            'low' => 'bg-gradient-success',
            default => 'bg-gradient-secondary'
        };
    }

    public function getQuirkTypeIcon(): string
    {
        return match($this->quirk_type) {
            'parameter_naming' => 'fa-font',
            'tr069_compliance' => 'fa-book',
            'performance' => 'fa-tachometer-alt',
            'connectivity' => 'fa-wifi',
            'security' => 'fa-shield-alt',
            'firmware' => 'fa-microchip',
            default => 'fa-exclamation-triangle'
        };
    }

    public function affectsProduct(int $productId, ?string $firmwareVersion = null): bool
    {
        if ($this->product_id && $this->product_id !== $productId) {
            return false;
        }

        if ($firmwareVersion && $this->firmware_versions_affected) {
            $affectedVersions = explode(',', $this->firmware_versions_affected);
            return in_array($firmwareVersion, array_map('trim', $affectedVersions));
        }

        return true;
    }
}
