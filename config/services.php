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

    'apify' => [
        'api_token' => env('APIFY_API_TOKEN'),
    ],

    'appsumo' => [
        'username' => env('APPSUMO_USERNAME'),
        'password' => env('APPSUMO_PASSWORD'),
    ],

    'axiom' => [
        'api_token' => env('AXIOM_API_TOKEN'),
    ],

    'bunnycdn' => [
        'api_key' => env('BUNNYCDN_API_KEY'),
        'zone' => env('BUNNYCDN_ZONE'),
    ],

    'cloudflare' => [
        'api_key' => env('CLOUDFLARE_API_KEY'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'kv' => [
            'customer_site' => env('CLOUDFLARE_CUSTOMER_SITE_KV_NAMESPACE'),
            'shopify_app_proxy' => env('CLOUDFLARE_SHOPIFY_APP_PROXY_KV_NAMESPACE'),
            'customer_site_cache' => env('CLOUDFLARE_CUSTOMER_SITE_CACHE_KV_NAMESPACE'),
        ],
        'customer_site_kv_namespace' => env('CLOUDFLARE_CUSTOMER_SITE_KV_NAMESPACE'),
        'shopify_app_proxy_kv_namespace' => env('CLOUDFLARE_SHOPIFY_APP_PROXY_KV_NAMESPACE'),
    ],

    'customerio' => [
        'site_id' => env('CUSTOMERIO_SITE_ID'),
        'track_key' => env('CUSTOMERIO_TRACK_KEY'),
        'app_key' => env('CUSTOMERIO_APP_KEY'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => '',
    ],

    'google' => [
        'customer_data_platform' => env('GOOGLE_CUSTOMER_DATA_PLATFORM'),
    ],

    'google_analytics' => [
        'app_property_id' => env('GOOGLE_ANALYTICS_APP_PROPERTY_ID'),
        'static_property_id' => env('GOOGLE_ANALYTICS_STATIC_PROPERTY_ID'),
    ],

    'growthbook' => [
        'webhook_secret' => env('GROWTHBOOK_WEBHOOK_SECRET'),
    ],

    'iframely' => [
        'api_key' => env('IFRAMELY_API_KEY'),
    ],

    'integration-app' => [
        'signing_key' => env('INTEGRATION_APP_SIGNING_KEY'),

        'workspace_key' => env('INTEGRATION_APP_WORKSPACE_KEY'),
    ],

    'intercom' => [
        'app_id' => env('INTERCOM_APP_ID'),
        'access_token' => env('INTERCOM_ACCESS_TOKEN'),
        'identity_verification_secret' => env('INTERCOM_IDENTITY_VERIFICATION_SECRET'),
    ],

    'jwt' => [
        'private_key' => env('JWT_PRIVATE_KEY'),
        'public_key' => env('JWT_PUBLIC_KEY'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => '',
    ],

    'logtail' => [
        'token' => env('LOGTAIL_TOKEN'),
    ],

    'matomo' => [
        'token' => env('MATOMO_TOKEN'),
        'app_site_id' => env('MATOMO_APP_SITE_ID'),
        'static_site_id' => env('MATOMO_STATIC_SITE_ID'),
    ],

    'paragon' => [
        'signing_key' => env('PARAGON_SIGNING_KEY'),

        'project_id' => env('PARAGON_PROJECT_ID'),

        'workflows' => [
            'prophet' => [
                'send_cold_email' => env('PARAGON_WORKFLOW_SEND_COLD_EMAIL'),
            ],
        ],
    ],

    'postmark' => [
        'account_token' => env('POSTMARK_ACCOUNT_TOKEN'),
        'app_server_token' => env('POSTMARK_APP_SERVER_TOKEN'),
        'subscriptions_server_token' => env('POSTMARK_SUBSCRIPTIONS_SERVER_TOKEN'),
        'token' => '',
    ],

    'revert' => [
        'token' => env('REVERT_TOKEN'),
        'public_token' => env('REVERT_PUBLIC_TOKEN'),
    ],

    'rudderstack' => [
        'write_key' => env('RUDDERSTACK_WRITE_KEY', 'null'),
        'data_plane_url' => env('RUDDERSTACK_DATA_PLANE_URL', 'https://storipresspmvx.dataplane.rudderstack.com'),
    ],

    'segment' => [
        'write_key' => env('SEGMENT_WRITE_KEY', null),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    'sentry' => [
        'token' => env('SENTRY_API_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'shopify' => [
        'client_id' => env('SHOPIFY_CLIENT_ID'),
        'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
        'redirect' => '',
    ],

    'slack' => [
        'channel_id' => env('SLACK_CHANNEL_ID'),
        'token' => env('SLACK_TOKEN'),
    ],

    'slack2' => [
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'redirect' => '',
    ],

    'storipress' => [
        'api_key' => env('STORIPRESS_API_KEY'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_KEY'),
        'client_secret' => env('TWITTER_SECRET'),
        'redirect' => '',
    ],

    'twitter-storipress' => [
        'client_id' => env('TWITTER_KEY'),
        'client_secret' => env('TWITTER_SECRET'),
        'redirect' => '',
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
        'secret_key' => env('UNSPLASH_SECRET_KEY'),
    ],

    'webflow' => [
        'client_id' => env('WEBFLOW_CLIENT_ID'),
        'client_secret' => env('WEBFLOW_CLIENT_SECRET'),
        'redirect' => null,
    ],

];
