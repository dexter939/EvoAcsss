<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ACS API Key
    |--------------------------------------------------------------------------
    |
    | The API key used to authenticate API requests.
    | This key is used by the ApiKeyAuth middleware.
    |
    */
    'api_key' => env('ACS_API_KEY', 'acs-secret-key-change-in-production'),

    /*
    |--------------------------------------------------------------------------
    | USP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for TR-369 USP (User Services Platform) protocol.
    |
    */
    'usp' => [
        'controller_endpoint_id' => env('USP_CONTROLLER_ENDPOINT_ID', 'proto::acs-controller-001'),
        'mqtt_enabled' => env('USP_MQTT_ENABLED', true),
        'websocket_enabled' => env('USP_WEBSOCKET_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different endpoints. Values are requests per minute.
    | Set to 0 to disable rate limiting for a specific limiter.
    |
    */
    'rate_limits' => [
        'api' => env('RATE_LIMIT_API', 60),
        'tr069' => env('RATE_LIMIT_TR069', 300),
        'login' => env('RATE_LIMIT_LOGIN', 5),
        'mobile' => env('RATE_LIMIT_MOBILE', 120),
        'websocket' => env('RATE_LIMIT_WEBSOCKET', 60),
        'bulk' => env('RATE_LIMIT_BULK', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Security Settings
    |--------------------------------------------------------------------------
    |
    | Auto-ban settings for rate limit violations.
    |
    */
    'rate_limit_security' => [
        'max_violations' => env('RATE_LIMIT_MAX_VIOLATIONS', 3),
        'ban_duration_minutes' => env('RATE_LIMIT_BAN_DURATION', 60),
    ],
];
