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

    'enabled' => env('TENANT_ENABLED', true),

    'enforce_isolation' => env('TENANT_ENFORCE_ISOLATION', true),

    'require_tenant' => env('TENANT_REQUIRE_TENANT', true),

    'require_session_tenant' => env('TENANT_REQUIRE_SESSION_TENANT', true),

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
        'secret_grace_hours' => env('TENANT_SECRET_GRACE_HOURS', 24),
        'anomaly_detection' => env('TENANT_ANOMALY_DETECTION', true),
        'alert_on_anomaly' => env('TENANT_ALERT_ON_ANOMALY', true),
        'alert_channels' => explode(',', env('TENANT_ALERT_CHANNELS', 'log,database')),
        'alert_emails' => array_filter(explode(',', env('TENANT_ALERT_EMAILS', ''))),
        'webhook_url' => env('TENANT_SECURITY_WEBHOOK_URL'),
        'webhook_secret' => env('TENANT_SECURITY_WEBHOOK_SECRET'),
        'failed_auth_threshold' => env('TENANT_FAILED_AUTH_THRESHOLD', 5),
        'rate_limit_threshold' => env('TENANT_RATE_LIMIT_THRESHOLD', 10),
        'cross_tenant_threshold' => env('TENANT_CROSS_TENANT_THRESHOLD', 1),
        'unusual_ip_threshold' => env('TENANT_UNUSUAL_IP_THRESHOLD', 10),
        'detection_window' => env('TENANT_DETECTION_WINDOW_MINUTES', 15),
    ],
];
