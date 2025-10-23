<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterProduct extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'model_name',
        'wifi_standard',
        'max_speed',
        'release_year',
        'price_usd',
        'key_features',
        'product_line',
        'form_factor',
        'mesh_support',
        'gaming_features',
        'notes'
    ];

    protected $casts = [
        'mesh_support' => 'boolean',
        'gaming_features' => 'boolean',
        'price_usd' => 'decimal:2',
        'release_year' => 'integer'
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(RouterManufacturer::class);
    }

    public function getWifiStandardBadgeClass(): string
    {
        return match($this->wifi_standard) {
            'WiFi 7' => 'bg-gradient-danger',
            'WiFi 6E' => 'bg-gradient-warning',
            'WiFi 6' => 'bg-gradient-info',
            'WiFi 5' => 'bg-gradient-secondary',
            default => 'bg-gradient-light'
        };
    }

    public function getFormattedPrice(): string
    {
        return $this->price_usd ? '$' . number_format($this->price_usd, 0) : 'N/A';
    }

    public function compatibilities()
    {
        return $this->hasMany(FirmwareCompatibility::class, 'product_id');
    }

    public function quirks()
    {
        return $this->hasMany(VendorQuirk::class, 'product_id');
    }

    public function templates()
    {
        return $this->hasMany(ConfigurationTemplateLibrary::class, 'product_id');
    }

    public function getCompatibleFirmwareCount(): int
    {
        return $this->compatibilities()
            ->where('compatibility_status', 'compatible')
            ->count();
    }
}
