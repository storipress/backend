<?php

return [

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#dsn
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // capture release as git sha
    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#release
    'release' => is_dir(base_path('.git')) ? trim(exec('git --git-dir '.base_path('.git').' log --pretty="%h" -n1 HEAD')) : null, // @phpstan-ignore-line

    // when left empty or `null` the Laravel environment will be used
    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#environment
    'environment' => env('SENTRY_ENVIRONMENT'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#max-breadcrumbs
    'max_breadcrumbs' => 100,

    // @see: https://docs.sentry.io/platforms/php/configuration/options/#attach-stacktrace
    'attach_stacktrace' => true,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send-default-pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', true),

    // @see: https://docs.sentry.io/platforms/php/configuration/options/#max-request-body-size
    'max_request_body_size' => 'always',

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#max-value-length
    'max_value_length' => 4096,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#context-lines
    'context_lines' => 16,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces-sample-rate
    // @see: https://docs.sentry.io/platforms/php/configuration/sampling/#inheritance
    'traces_sample_rate' => is_numeric(($rate = env('SENTRY_TRACES_SAMPLE_RATE'))) ? (float) $rate : null,

    'profiles_sample_rate' => is_numeric(($rate = env('SENTRY_TRACES_SAMPLE_RATE'))) ? (float) $rate : null,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/laravel-options/#controller-base-namespace
    'controllers_base_namespace' => env('SENTRY_CONTROLLERS_BASE_NAMESPACE', 'App\\Http\\Controllers'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/laravel-options/#breadcrumbs
    'breadcrumbs' => [
        // Capture Laravel logs in breadcrumbs
        'logs' => true,

        // Capture Laravel cache events in breadcrumbs
        'cache' => true,

        // Capture Livewire components in breadcrumbs
        'livewire' => true,

        // Capture SQL queries in breadcrumbs
        'sql_queries' => true,

        // Capture bindings on SQL queries logged in breadcrumbs
        'sql_bindings' => true,

        // Capture queue job information in breadcrumbs
        'queue_info' => true,

        // Capture command information in breadcrumbs
        'command_info' => true,

        // Capture HTTP client requests information in breadcrumbs
        'http_client_requests' => true,

        // Capture storage access as breadcrumbs
        'storage' => true,
    ],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/laravel-options/#more-configuration
    'tracing' => [
        // Trace queue jobs as their own transactions
        'queue_job_transactions' => true,

        // Capture queue jobs as spans when executed on the sync driver
        'queue_jobs' => true,

        // Capture SQL queries as spans
        'sql_queries' => true,

        // Capture SQL query bindings (parameters) in SQL query spans
        'sql_bindings' => true,

        // Try to find out where the SQL query originated from and add it to the query spans
        'sql_origin' => true,

        // Capture views as spans
        'views' => true,

        // Capture Livewire components as spans
        'livewire' => true,

        // Capture HTTP client requests as spans
        'http_client_requests' => true,

        // Capture Redis operations as spans (this enables Redis events in Laravel)
        'redis_commands' => true,

        // Try to find out where the Redis command originated from and add it to the command spans
        'redis_origin' => true,

        // Indicates if the tracing integrations supplied by Sentry should be loaded
        'default_integrations' => true,

        // Indicates that requests without a matching route should be traced
        'missing_routes' => true,

        // Capture storage access as spans
        'storage' => true,

        // Indicates if the performance trace should continue after the response has been sent to the user until the application terminates
        // This is required to capture any spans that are created after the response has been sent like queue jobs dispatched using `dispatch(...)->afterResponse()` for example
        'continue_after_response' => true,
    ],

];
