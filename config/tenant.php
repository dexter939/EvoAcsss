<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the multi-tenant functionality for the ACS system.
    | Enable/disable features and configure tenant resolution methods.
    |
    */

    'enabled' => env('TENANT_ENABLED', false),

    'enforce_isolation' => env('TENANT_ENFORCE_ISOLATION', false),

    'require_tenant' => env('TENANT_REQUIRE_TENANT', false),

    'default_id' => env('TENANT_DEFAULT_ID', 1),

    'header_name' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Discovery Methods
    |--------------------------------------------------------------------------
    |
    | Configure which methods are used to identify tenants.
    | Available: subdomain, header, token, user
    |
    */
    'discovery' => [
        'subdomain' => env('TENANT_DISCOVERY_SUBDOMAIN', true),
        'header' => env('TENANT_DISCOVERY_HEADER', true),
        'token' => env('TENANT_DISCOVERY_TOKEN', true),
        'user' => env('TENANT_DISCOVERY_USER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    |
    | Routes that don't require tenant identification.
    |
    */
    'public_routes' => [
        'health',
        'up',
        'metrics',
        'tr069',
        'tr069/*',
        'usp',
        'login',
        'register',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('TENANT_CACHE_ENABLED', true),
        'ttl' => env('TENANT_CACHE_TTL', 300),
        'prefix' => 'tenant:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'log_cross_tenant_attempts' => env('TENANT_LOG_CROSS_TENANT', true),
        'secret_rotation_days' => env('TENANT_SECRET_ROTATION_DAYS', 90),
        'anomaly_detection' => env('TENANT_ANOMALY_DETECTION', true),
    ],
];
