<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WAHA (WhatsApp HTTP API) Configuration
    |--------------------------------------------------------------------------
    |
    | WAHA is a self-hosted WhatsApp REST API running via Docker.
    | Set WAHA_ENABLED=true and configure the URL/key to activate.
    |
    */

    'enabled'  => env('WAHA_ENABLED', false),
    'base_url' => env('WAHA_BASE_URL', 'http://localhost:3000'),
    'api_key'  => env('WAHA_API_KEY', ''),
    'session'  => env('WAHA_SESSION', 'default'),
];
