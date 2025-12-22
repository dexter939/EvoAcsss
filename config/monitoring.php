<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for application monitoring, error tracking, and metrics.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Prometheus Metrics
    |--------------------------------------------------------------------------
    */
    'prometheus' => [
        'enabled' => env('METRICS_ENABLED', false),
        'namespace' => env('METRICS_NAMESPACE', 'acs'),
        'endpoint' => env('METRICS_ENDPOINT', '/metrics'),
        'auth_enabled' => env('METRICS_AUTH_ENABLED', false),
        'auth_token' => env('METRICS_AUTH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sentry Error Tracking
    |--------------------------------------------------------------------------
    */
    'sentry' => [
        'enabled' => env('SENTRY_ENABLED', false),
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
        'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
        'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
        'release' => env('SENTRY_RELEASE', env('APP_VERSION', '1.0.0')),
        'ignore_exceptions' => [
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ],
        'ignore_transactions' => [
            '/health',
            '/metrics',
            '/up',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    */
    'health' => [
        'checks' => [
            'database' => env('HEALTH_CHECK_DATABASE', true),
            'redis' => env('HEALTH_CHECK_REDIS', true),
            'queue' => env('HEALTH_CHECK_QUEUE', true),
            'storage' => env('HEALTH_CHECK_STORAGE', true),
        ],
        'cache_ttl' => env('HEALTH_CHECK_CACHE_TTL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Thresholds
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'device_offline_threshold' => env('ALERT_DEVICE_OFFLINE_THRESHOLD', 10),
        'queue_backlog_threshold' => env('ALERT_QUEUE_BACKLOG_THRESHOLD', 1000),
        'error_rate_threshold' => env('ALERT_ERROR_RATE_THRESHOLD', 5),
        'response_time_threshold' => env('ALERT_RESPONSE_TIME_THRESHOLD', 2000),
    ],
];
