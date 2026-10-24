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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'discord' => [
        'bot_name' => env('DISCORD_BOT_NAME', 'Nạp Carot Monitor'),
        'bot_avatar_url' => env('DISCORD_BOT_AVATAR_URL'),
        'report_queue_success' => env('DISCORD_REPORT_QUEUE_SUCCESS', false),
        'channels' => [
            'queue' => env('DISCORD_WEBHOOK_QUEUE'),
            'info' => env('DISCORD_WEBHOOK_INFO'),
            'ops' => env('DISCORD_WEBHOOK_OPS'),
            'security' => env('DISCORD_WEBHOOK_SECURITY'),
            'alerts' => env('DISCORD_WEBHOOK_ALERTS'),
            'recovered' => env('DISCORD_WEBHOOK_RECOVERED'),
            'staging' => env('DISCORD_WEBHOOK_STAGING'),
            'feedback' => env('DISCORD_WEBHOOK_FEEDBACK') ?: env('DISCORD_WEBHOOK_INFO'),
            'activity' => env('DISCORD_WEBHOOK_ACTIVITY') ?: env('DISCORD_WEBHOOK_INFO'),
        ],
        'context' => [
            'app_name' => env('APP_NAME', 'Nạp Carot'),
            'app_env' => env('APP_ENV', 'production'),
            'app_url' => env('APP_URL'),
            'server_name' => env('DISCORD_SERVER_NAME', gethostname() ?: php_uname('n')),
            'server_ip' => env('DISCORD_SERVER_IP'),
            'server_role' => env('DISCORD_SERVER_ROLE', 'app'),
            'server_region' => env('DISCORD_SERVER_REGION'),
        ],
    ],

    'nro_api_rate_limits' => [
        'catalog' => (int) env('NRO_GAME_DATA_REQUESTS_PER_MINUTE', 600),
        'poll' => (int) env('NRO_GAME_DATA_POLLS_PER_MINUTE', 3600),
        'uploads' => (int) env('NRO_IMAGE_UPLOADS_PER_MINUTE', 120),
        'notifications' => (int) env('NRO_NOTIFICATIONS_PER_MINUTE', 600),
    ],

    'nro_bot' => [
        'heartbeat_key' => env('NRO_BOT_HEARTBEAT_KEY'),
        'heartbeat_interval_seconds' => (int) env('NRO_BOT_HEARTBEAT_INTERVAL_SECONDS', 30),
        'missed_heartbeats' => (int) env('NRO_BOT_MISSED_HEARTBEATS', 5),
    ],

    'nro_notifications' => [
        'cache_store' => env('NRO_NOTIFICATION_CACHE_STORE'),
        'catalog_cache_ttl_seconds' => (int) env('NRO_NOTIFICATION_CATALOG_CACHE_TTL_SECONDS', 60),
    ],

    'zalo_bot' => [
        'enabled' => env('ZALO_BOT_ENABLED', false),
        'messages_url' => env('ZALO_BOT_MESSAGES_URL', 'https://zalo.tienichnro.com/api/messages'),
        'authorization' => env('ZALO_BOT_AUTHORIZATION'),
        'account_id' => (int) env('ZALO_BOT_ACCOUNT_ID', 1),
        'box_id' => (string) env('ZALO_BOT_BOX_ID', ''),
        'recipient_type' => env('ZALO_BOT_RECIPIENT_TYPE', 'group'),
        'registration_key' => env('ZALO_BOT_REGISTRATION_KEY'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'default_chat_id' => env('TELEGRAM_DEFAULT_CHAT_ID'),
    ],

    'internal_cron' => [
        'key' => env('AUTOCRON_INTERNAL_KEY'),
    ],

];
