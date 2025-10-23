<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterManufacturer extends Model
{
    protected $fillable = [
        'name',
        'oui_prefix',
        'category',
        'country',
        'product_lines',
        'tr069_support',
        'tr369_support',
        'notes'
    ];

    protected $casts = [
        'tr069_support' => 'boolean',
        'tr369_support' => 'boolean'
    ];

    public function getOuiPrefixesArray(): array
    {
        return $this->oui_prefix ? explode(',', $this->oui_prefix) : [];
    }

    public function getCategoryBadgeClass(): string
    {
        return match($this->category) {
            'premium' => 'bg-gradient-dark',
            'enterprise' => 'bg-gradient-primary',
            'mainstream' => 'bg-gradient-info',
            'budget' => 'bg-gradient-success',
            'mesh' => 'bg-gradient-warning',
            'prosumer' => 'bg-gradient-secondary',
            'telco' => 'bg-gradient-danger',
            'powerline' => 'bg-gradient-light',
            default => 'bg-gradient-secondary'
        };
    }

    public function products()
    {
        return $this->hasMany(RouterProduct::class, 'manufacturer_id');
    }

    public function quirks()
    {
        return $this->hasMany(VendorQuirk::class, 'manufacturer_id');
    }

    public function templates()
    {
        return $this->hasMany(ConfigurationTemplateLibrary::class, 'manufacturer_id');
    }

    public function getActiveQuirksCount(): int
    {
        return $this->quirks()->where('is_active', true)->count();
    }
}
