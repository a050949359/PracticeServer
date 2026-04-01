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

    'vertex_ai' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'project_id' => env('VERTEX_AI_PROJECT_ID'),
        'location' => env('VERTEX_AI_LOCATION', 'us-central1'),
        'model' => env('VERTEX_AI_MODEL', 'gemini-2.0-flash-001'),
    ],

    'google_drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'scope' => env('GOOGLE_DRIVE_SCOPE', 'openid email profile https://www.googleapis.com/auth/drive.file'),
        'oauth_client_id' => env('GOOGLE_DRIVE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('GOOGLE_DRIVE_OAUTH_REDIRECT_URI'),
    ],

    'firestore' => [
        'project_id' => env('FIRESTORE_PROJECT_ID', env('VERTEX_AI_PROJECT_ID')),
        'credentials_path' => env('FIRESTORE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),
        'database' => env('FIRESTORE_DATABASE', '(default)'),
        'task_collection' => env('FIRESTORE_TASK_COLLECTION', 'csv_export_tasks'),
        'sync_enabled' => (bool) env('FIRESTORE_SYNC_ENABLED', false),
    ],

    'influxdb' => [
        'url' => env('INFLUXDB_URL', 'http://127.0.0.1:8086'),
        'token' => env('INFLUXDB_TOKEN'),
        'database' => env('INFLUXDB_DATABASE', 'csv_export_metrics'),
        'sync_enabled' => (bool) env('INFLUXDB_SYNC_ENABLED', false),
    ],

];
