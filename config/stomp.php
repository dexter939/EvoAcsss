<?php

return [

    /*
    |--------------------------------------------------------------------------
    | STOMP Broker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for STOMP (Simple Text Oriented Messaging Protocol) broker
    | Used for TR-262 CWMP-STOMP Binding implementation
    |
    */

    'host' => env('STOMP_HOST', 'localhost'),
    'port' => env('STOMP_PORT', 61613),
    
    'username' => env('STOMP_USERNAME', 'guest'),
    'password' => env('STOMP_PASSWORD', 'guest'),

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Management API Configuration
    |--------------------------------------------------------------------------
    |
    | Used for broker introspection and real-time metrics collection
    | Enable RabbitMQ Management Plugin: rabbitmq-plugins enable rabbitmq_management
    |
    */

    'rabbitmq' => [
        'management_host' => env('RABBITMQ_MANAGEMENT_HOST', 'localhost'),
        'management_port' => env('RABBITMQ_MANAGEMENT_PORT', 15672),
        'username' => env('RABBITMQ_USERNAME', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        // How often to poll broker metrics (minutes)
        'poll_interval' => env('STOMP_POLL_INTERVAL', 1),
        
        // Metrics retention (days)
        'retention_days' => env('STOMP_METRICS_RETENTION', 30),
    ],

];
