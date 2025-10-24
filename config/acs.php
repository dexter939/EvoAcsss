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
];
