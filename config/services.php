<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'greenapi' => [
        'url' => env('API_URL', 'https://1105.api.green-api.com'),
        'id_instance' => env('ID_INSTANCE'),
        'api_token' => env('API_TOKEN_INSTANCE'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'vector_store_id' => env('VECTOR_STORE_ID'),
        'use_proxy' => env('USE_PROXY', false),
        'proxy_host' => env('PROXY_HOST'),
        'proxy_port' => env('PROXY_PORT'),
    ],

];
