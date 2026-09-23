<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ninerouter' => [
        'base_url' => env('NINEROUTER_BASE_URL'),
        'api_key' => env('NINEROUTER_API_KEY'),
        'model' => env('NINEROUTER_MODEL'),
        'connect_timeout' => (int) env('NINEROUTER_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('NINEROUTER_REQUEST_TIMEOUT', 60),
        'streaming' => filter_var(env('NINEROUTER_STREAMING', false), FILTER_VALIDATE_BOOL),
    ],

    'assistant' => [
        'enabled' => filter_var(env('ASSISTANT_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

];
