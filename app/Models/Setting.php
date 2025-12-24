<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type, $key);
        });
    }

    public static function set(string $key, $value): void
    {
        $setting = static::where('key', $key)->first();
        $type = $setting?->type ?? 'string';
        
        $storedValue = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            in_array($key, ['acs_password', 'connection_request_password']) => encrypt((string) $value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );

        Cache::forget("setting.{$key}");
        Cache::forget('settings.all');
    }

    public static function getAll(): array
    {
        return Cache::remember('settings.all', 3600, function () {
            return static::all()->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type, $setting->key)];
            })->toArray();
        });
    }

    protected static function castValue($value, string $type, ?string $key = null)
    {
        if (in_array($key, ['acs_password', 'connection_request_password']) && !empty($value)) {
            try {
                $value = decrypt($value);
            } catch (\Exception $e) {
            }
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}
