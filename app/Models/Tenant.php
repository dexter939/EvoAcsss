<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'settings',
        'api_key',
        'api_secret',
        'is_active',
        'max_devices',
        'max_users',
        'contact_email',
        'contact_phone',
        'notes',
        'api_secret_rotated_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'max_devices' => 'integer',
        'max_users' => 'integer',
        'api_secret_rotated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
            if (empty($tenant->api_key)) {
                $tenant->api_key = Str::random(64);
            }
            if (empty($tenant->api_secret)) {
                $tenant->api_secret = Str::random(128);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(CpeDevice::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(TenantCredential::class);
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    public function rotateApiSecret(): void
    {
        $this->update([
            'api_secret' => Str::random(128),
            'api_secret_rotated_at' => now(),
        ]);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function isAtDeviceLimit(): bool
    {
        return $this->devices()->count() >= $this->max_devices;
    }

    public function isAtUserLimit(): bool
    {
        return $this->users()->count() >= $this->max_users;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeBySubdomain($query, string $subdomain)
    {
        return $query->where('subdomain', $subdomain);
    }

    public static function findByApiKey(string $apiKey): ?self
    {
        return static::where('api_key', $apiKey)->where('is_active', true)->first();
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }

    public static function findByDomain(string $domain): ?self
    {
        return static::where('domain', $domain)->where('is_active', true)->first();
    }

    public static function findBySubdomain(string $subdomain): ?self
    {
        return static::where('subdomain', $subdomain)->where('is_active', true)->first();
    }
}
