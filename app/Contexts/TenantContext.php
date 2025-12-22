<?php

namespace App\Contexts;

use App\Models\Tenant;

class TenantContext
{
    private static ?Tenant $current = null;

    public static function set(?Tenant $tenant): void
    {
        self::$current = $tenant;
    }

    public static function get(): ?Tenant
    {
        return self::$current;
    }

    public static function id(): ?int
    {
        return self::$current?->id;
    }

    public static function uuid(): ?string
    {
        return self::$current?->uuid;
    }

    public static function slug(): ?string
    {
        return self::$current?->slug;
    }

    public static function check(): bool
    {
        return self::$current !== null;
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    public static function is(Tenant $tenant): bool
    {
        return self::$current && self::$current->id === $tenant->id;
    }

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        return self::$current?->getSetting($key, $default) ?? $default;
    }

    public static function require(): Tenant
    {
        if (!self::$current) {
            throw new \RuntimeException('No tenant context available');
        }

        return self::$current;
    }
}
