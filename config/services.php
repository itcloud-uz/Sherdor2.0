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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'pin_bot_token' => env('PIN_BOT_TOKEN', env('TELEGRAM_PIN_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'))),
        'pin_chat_id' => env('PIN_CHAT_ID', env('TELEGRAM_PIN_CHAT_ID', env('TELEGRAM_CHAT_ID'))),
        'backup_bot_token' => env('BACKUP_BOT_TOKEN', env('TELEGRAM_BACKUP_BOT_TOKEN')),
        'backup_chat_id' => env('BACKUP_CHAT_ID', env('TELEGRAM_BACKUP_CHAT_ID')),
        'finance_bot_token' => env('FINANCE_BOT_TOKEN', env('TELEGRAM_FINANCE_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'))),
    ],

    'bot' => [
        'secret_key' => env('BOT_SECRET_KEY', 'default_secret_key_for_dev'),
    ],

];
