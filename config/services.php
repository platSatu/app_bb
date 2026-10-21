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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Service WhatsApp v2 (go_bizbos). url = alamat internal (API), public_url = alamat yang dibuka browser (SSE & media).
    'wa' => [
        'url' => env('WA_SERVICE_URL', 'http://127.0.0.1:8090'),
        'public_url' => env('WA_PUBLIC_URL', env('WA_SERVICE_URL', 'http://127.0.0.1:8090')),
        'api_secret' => env('WA_API_SECRET'),
        'access_secret' => env('WA_ACCESS_SECRET'),
        'webhook_secret' => env('WA_WEBHOOK_SECRET'),
        'blast_daily_cap' => (int) env('WA_BLAST_DAILY_CAP', 200),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Kunci API untuk fe-konexa (/api/frontend/*). Harus sama dengan TELEIOS_API_KEY di .env fe-konexa.
    'frontend' => [
        'key' => env('FRONTEND_API_KEY'),
    ],

    // Google Analytics bizbos (G-XXXX), dipasang di semua halaman publik page. Kosong = tidak dipasang.
    'google_analytics' => [
        'id' => env('GOOGLE_ANALYTICS_ID'),
    ],

];
