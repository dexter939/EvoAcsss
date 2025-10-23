<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigurationTemplateLibrary extends Model
{
    protected $table = 'configuration_templates_library';

    protected $fillable = [
        'manufacturer_id',
        'product_id',
        'template_name',
        'template_category',
        'description',
        'protocol',
        'parameter_values',
        'applicable_firmware_versions',
        'required_capabilities',
        'is_official',
        'is_tested',
        'usage_count',
        'created_by',
        'usage_notes',
        'rating'
    ];

    protected $casts = [
        'parameter_values' => 'array',
        'applicable_firmware_versions' => 'array',
        'required_capabilities' => 'array',
        'is_official' => 'boolean',
        'is_tested' => 'boolean',
        'usage_count' => 'integer',
        'rating' => 'integer'
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(RouterManufacturer::class, 'manufacturer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(RouterProduct::class, 'product_id');
    }

    public function getCategoryBadgeClass(): string
    {
        return match($this->template_category) {
            'security' => 'bg-gradient-danger',
            'qos' => 'bg-gradient-warning',
            'voip' => 'bg-gradient-info',
            'wifi' => 'bg-gradient-primary',
            'basic' => 'bg-gradient-success',
            'advanced' => 'bg-gradient-dark',
            default => 'bg-gradient-secondary'
        };
    }

    public function getProtocolBadgeClass(): string
    {
        return match($this->protocol) {
            'TR-069' => 'bg-gradient-primary',
            'TR-369' => 'bg-gradient-info',
            'TR-104' => 'bg-gradient-success',
            'TR-181' => 'bg-gradient-warning',
            default => 'bg-gradient-secondary'
        };
    }

    public function incrementUsageCount(): void
    {
        $this->increment('usage_count');
    }

    public function isApplicableToFirmware(string $firmwareVersion): bool
    {
        if (!$this->applicable_firmware_versions) {
            return true;
        }

        return in_array($firmwareVersion, $this->applicable_firmware_versions);
    }

    public function hasRequiredCapabilities(array $deviceCapabilities): bool
    {
        if (!$this->required_capabilities) {
            return true;
        }

        foreach ($this->required_capabilities as $capability) {
            if (!in_array($capability, $deviceCapabilities)) {
                return false;
            }
        }

        return true;
    }
}
