<?php

use App\Jobs\Typesense\MakeSearchable;
use App\Jobs\Typesense\RemoveFromSearch;
use App\Listeners\Partners\Postmark\WebhookReceived\TransformIntoSubscriberEvent;
use App\Listeners\Partners\Postmark\WebhookReceiving\SaveEventToDatabase;
use App\Listeners\Partners\Shopify\WebhookReceived\HandleCustomersCreate;
use App\Listeners\Partners\Shopify\WebhookReceived\HandleCustomersUpdate;
use App\Listeners\StripeWebhookHandled\HandleSubscriptionChanged;
use App\Listeners\StripeWebhookReceived\HandleInvoiceCreated;

$isProd = env('APP_ENV') === 'production';

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        'storipress_horizon:',
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['auth.basic'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => $isProd ? [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 4320,
        'failed' => 4320,
        'monitored' => 4320,
    ] : [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 1440,
        'failed' => 1440,
        'monitored' => 1440,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => $isProd ? [
        HandleCustomersCreate::class,
        HandleCustomersUpdate::class,
        SaveEventToDatabase::class,
        TransformIntoSubscriberEvent::class,
        MakeSearchable::class,
        RemoveFromSearch::class,
    ] : [
        HandleInvoiceCreated::class,
        HandleSubscriptionChanged::class,
        SaveEventToDatabase::class,
        TransformIntoSubscriberEvent::class,
        MakeSearchable::class,
        RemoveFromSearch::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            // do not use auto, it will result in errors
            // @see https://github.com/laravel/horizon/issues/1075
            'balance' => 'simple',
            'minProcesses' => 2,
            'maxProcesses' => 5,
            'balanceMaxShift' => 2,
            'balanceCooldown' => 5,
            'maxTime' => 60 * 60,
            'maxJobs' => 128,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 0,
            'sleep' => 1,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 6,
                'memory' => 128,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
                'memory' => 96,
            ],
        ],

        'development' => [
            'supervisor-1' => [
                'maxProcesses' => 6,
                'memory' => 64,
            ],
        ],

        'testing' => [
            'supervisor-1' => [
                'memory' => 64,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'memory' => 64,
            ],
        ],
    ],
];
