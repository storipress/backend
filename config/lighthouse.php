<?php

use App\GraphQL\Extends\ValidateDirective;
use App\Http\Middleware\BuilderAuthenticate;
use App\Http\Middleware\CatchDefinitionException;
use App\Http\Middleware\GraphQLHttpMethodNotAllowed;
use GraphQL\Validator\Rules\DisableIntrospection;
use Nuwave\Lighthouse\Execution\ReportingErrorHandler;
use Nuwave\Lighthouse\Execution\ValidationErrorHandler;
use Nuwave\Lighthouse\Http\Middleware\AcceptJson;
use Nuwave\Lighthouse\Schema\Directives\DropArgsDirective;
use Nuwave\Lighthouse\Schema\Directives\RenameArgsDirective;
use Nuwave\Lighthouse\Schema\Directives\SanitizeDirective;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Nuwave\Lighthouse\Schema\Directives\TransformArgsDirective;
use Nuwave\Lighthouse\Schema\Directives\TrimDirective;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the HTTP route that your GraphQL server responds to.
    | You may set `route` => false, to disable the default route
    | registration and take full control.
    |
    */

    'route' => [
        /*
         * The URI the endpoint responds to, e.g. mydomain.com/graphql.
         */
        'uri' => '/client/{client}/graphql',

        /*
         * Lighthouse creates a named route for convenient URL generation and redirects.
         */
        'name' => 'graphql',

        /*
         * Beware that middleware defined here runs before the GraphQL execution phase,
         * make sure to return spec-compliant responses in case an error is thrown.
         */
        'middleware' => [
            AcceptJson::class,

            // filter not allowed method
            GraphQLHttpMethodNotAllowed::class,

            // apply api middleware(throttle)
            'api',

            // multi-tenancy identify
            InitializeTenancyByPath::class,

            // authenticate builder requests
            BuilderAuthenticate::class,

            // catch DefinitionException errors
            CatchDefinitionException::class,

            // Logs every incoming GraphQL query.
            // \Nuwave\Lighthouse\Support\Http\Middleware\LogGraphQLQueries::class,
        ],

        /*
         * The `prefix`, `domain` and `where` configuration options are optional.
         */
        // 'prefix' => '',
        // 'domain' => '',
        // 'where' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | The guards to use for authenticating GraphQL requests, if needed.
    | Used in directives such as `@guard` or the `AttemptAuthentication` middleware.
    | Falls back to the Laravel default if `null`.
    |
    */

    'guard' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Schema Path
    |--------------------------------------------------------------------------
    |
    | Path to your .graphql schema file.
    | Additional schema files may be imported from within that file.
    |
    */

    'schema_path' => base_path('graphql/schema.graphql'),

    /*
    |--------------------------------------------------------------------------
    | Schema Cache
    |--------------------------------------------------------------------------
    |
    | A large part of schema generation consists of parsing and AST manipulation.
    | This operation is very expensive, so it is highly recommended enabling
    | caching of the final schema to optimize performance of large schemas.
    |
    */

    'schema_cache' => [
        /*
         * Setting to true enables schema caching.
         */
        'enable' => env('APP_ENV') !== 'local',

        /*
         * File path to store the lighthouse schema.
         */
        'path' => base_path('bootstrap/cache/lighthouse-schema.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Directive Tags
    |--------------------------------------------------------------------------
    |
    | Should the `@cache` directive use a tagged cache?
    |
    */

    'cache_directive_tags' => false,

    /*
    |--------------------------------------------------------------------------
    | Query Cache
    |--------------------------------------------------------------------------
    |
    | Caches the result of parsing incoming query strings to boost performance on subsequent requests.
    |
    */

    'query_cache' => [
        /*
         * Setting to true enables query caching.
         */
        'enable' => true,

        /*
         * Allows using a specific cache store, uses the app's default if set to null.
         */
        'store' => null,

        /*
         * Duration in seconds the query should remain cached, null means forever.
         */
        'ttl' => 60 * 60, // 1 hour in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Parse source location
    |--------------------------------------------------------------------------
    |
    | Should the source location be included in the AST nodes resulting from query parsing?
    | Setting this to `false` improves performance, but omits the key `locations` from errors,
    | see https://spec.graphql.org/October2021/#sec-Errors.Error-result-format.
    |
    */

    'parse_source_location' => true,

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These are the default namespaces where Lighthouse looks for classes to
    | extend functionality of the schema. You may pass in either a string
    | or an array, they are tried in order and the first match is used.
    |
    */

    'namespaces' => [
        'models' => [
            'App\\Models',
            'App\\Models\\Tenants',
        ],
        'queries' => [
            'App\\GraphQL\\Queries',
            'App\\GraphQL\\Queries\\Billing',
            'App\\GraphQL\\Queries\\Facebook',
            'App\\GraphQL\\Queries\\Link',
            'App\\GraphQL\\Queries\\Prophet',
            'App\\GraphQL\\Queries\\Revert',
            'App\\GraphQL\\Queries\\Scraper',
            'App\\GraphQL\\Queries\\Shopify',
            'App\\GraphQL\\Queries\\Subscriber',
            'App\\GraphQL\\Queries\\Webflow',
            'App\\GraphQL\\Queries\\WordPress',
        ],
        'mutations' => [
            'App\\GraphQL\\Mutations',
            'App\\GraphQL\\Mutations\\Account',
            'App\\GraphQL\\Mutations\\Article',
            'App\\GraphQL\\Mutations\\Article\\Thread',
            'App\\GraphQL\\Mutations\\Article\\Thread\\Note',
            'App\\GraphQL\\Mutations\\Auth',
            'App\\GraphQL\\Mutations\\Billing',
            'App\\GraphQL\\Mutations\\Block',
            'App\\GraphQL\\Mutations\\CustomDomain',
            'App\\GraphQL\\Mutations\\CustomField',
            'App\\GraphQL\\Mutations\\CustomFieldGroup',
            'App\\GraphQL\\Mutations\\CustomFieldValue',
            'App\\GraphQL\\Mutations\\Design',
            'App\\GraphQL\\Mutations\\Desk',
            'App\\GraphQL\\Mutations\\Generator',
            'App\\GraphQL\\Mutations\\Helper',
            'App\\GraphQL\\Mutations\\Integration',
            'App\\GraphQL\\Mutations\\Invitation',
            'App\\GraphQL\\Mutations\\Layout',
            'App\\GraphQL\\Mutations\\Link',
            'App\\GraphQL\\Mutations\\Linter',
            'App\\GraphQL\\Mutations\\Packages',
            'App\\GraphQL\\Mutations\\Page',
            'App\\GraphQL\\Mutations\\Paragon',
            'App\\GraphQL\\Mutations\\Redirection',
            'App\\GraphQL\\Mutations\\Release',
            'App\\GraphQL\\Mutations\\Revert',
            'App\\GraphQL\\Mutations\\Scraper',
            'App\\GraphQL\\Mutations\\Site',
            'App\\GraphQL\\Mutations\\Stage',
            'App\\GraphQL\\Mutations\\Subscriber',
            'App\\GraphQL\\Mutations\\Sync',
            'App\\GraphQL\\Mutations\\Tag',
            'App\\GraphQL\\Mutations\\Template',
            'App\\GraphQL\\Mutations\\Upload',
            'App\\GraphQL\\Mutations\\User',
            'App\\GraphQL\\Mutations\\Webflow',
            'App\\GraphQL\\Mutations\\WordPress',
        ],
        'subscriptions' => 'App\\GraphQL\\Subscriptions',
        'types' => 'App\\GraphQL\\Types',
        'interfaces' => 'App\\GraphQL\\Interfaces',
        'unions' => 'App\\GraphQL\\Unions',
        'scalars' => 'App\\GraphQL\\Scalars',
        'directives' => ['App\\GraphQL\\Directives'],
        'validators' => ['App\\GraphQL\\Validators'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Control how Lighthouse handles security related query validation.
    | Read more at https://webonyx.github.io/graphql-php/security/
    |
    */

    'security' => [
        'max_query_complexity' => 5099,

        'max_query_depth' => 19,

        'disable_introspection' => (bool) env('APP_DEBUG', false)
            ? DisableIntrospection::DISABLED
            : DisableIntrospection::ENABLED,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Set defaults for the pagination features within Lighthouse, such as
    | the @paginate directive, or paginated relation directives.
    |
    */

    'pagination' => [
        /*
         * Allow clients to query paginated lists without specifying the amount of items.
         * Setting this to `null` means clients have to explicitly ask for the count.
         */
        'default_count' => 10,

        /*
         * Limit the maximum amount of items that clients can request from paginated lists.
         * Setting this to `null` means the count is unrestricted.
         */
        'max_count' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Control the debug level as described in https://webonyx.github.io/graphql-php/error-handling/
    | Debugging is only applied if the global Laravel debug config is set to true.
    |
    | When you set this value through an environment variable, use the following reference table:
    |  0 => INCLUDE_NONE
    |  1 => INCLUDE_DEBUG_MESSAGE
    |  2 => INCLUDE_TRACE
    |  3 => INCLUDE_TRACE | INCLUDE_DEBUG_MESSAGE
    |  4 => RETHROW_INTERNAL_EXCEPTIONS
    |  5 => RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_DEBUG_MESSAGE
    |  6 => RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_TRACE
    |  7 => RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_TRACE | INCLUDE_DEBUG_MESSAGE
    |  8 => RETHROW_UNSAFE_EXCEPTIONS
    |  9 => RETHROW_UNSAFE_EXCEPTIONS | INCLUDE_DEBUG_MESSAGE
    | 10 => RETHROW_UNSAFE_EXCEPTIONS | INCLUDE_TRACE
    | 11 => RETHROW_UNSAFE_EXCEPTIONS | INCLUDE_TRACE | INCLUDE_DEBUG_MESSAGE
    | 12 => RETHROW_UNSAFE_EXCEPTIONS | RETHROW_INTERNAL_EXCEPTIONS
    | 13 => RETHROW_UNSAFE_EXCEPTIONS | RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_DEBUG_MESSAGE
    | 14 => RETHROW_UNSAFE_EXCEPTIONS | RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_TRACE
    | 15 => RETHROW_UNSAFE_EXCEPTIONS | RETHROW_INTERNAL_EXCEPTIONS | INCLUDE_TRACE | INCLUDE_DEBUG_MESSAGE
    |
    */

    'debug' => 11,

    /*
    |--------------------------------------------------------------------------
    | Error Handlers
    |--------------------------------------------------------------------------
    |
    | Register error handlers that receive the Errors that occur during execution
    | and handle them. You may use this to log, filter or format the errors.
    | The classes must implement \Nuwave\Lighthouse\Execution\ErrorHandler
    |
    */

    'error_handlers' => [
        ValidationErrorHandler::class,
        ReportingErrorHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Middleware
    |--------------------------------------------------------------------------
    |
    | Register global field middleware directives that wrap around every field.
    | Execution happens in the defined order, before other field middleware.
    | The classes must implement \Nuwave\Lighthouse\Support\Contracts\FieldMiddleware
    |
    */

    'field_middleware' => [
        TrimDirective::class,
        SanitizeDirective::class,
        ValidateDirective::class,
        TransformArgsDirective::class,
        SpreadDirective::class,
        RenameArgsDirective::class,
        DropArgsDirective::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global ID
    |--------------------------------------------------------------------------
    |
    | The name that is used for the global id field on the Node interface.
    | When creating a Relay compliant server, this must be named "id".
    |
    */

    'global_id_field' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Persisted Queries
    |--------------------------------------------------------------------------
    |
    | Lighthouse supports Automatic Persisted Queries (APQ), compatible with the
    | [Apollo implementation](https://www.apollographql.com/docs/apollo-server/performance/apq).
    | You may set this flag to either process or deny these queries.
    |
    */

    'persisted_queries' => true,

    /*
    |--------------------------------------------------------------------------
    | Transactional Mutations
    |--------------------------------------------------------------------------
    |
    | If set to true, built-in directives that mutate models will be
    | wrapped in a transaction to ensure atomicity.
    |
    */

    'transactional_mutations' => true,

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment Protection
    |--------------------------------------------------------------------------
    |
    | If set to true, mutations will use forceFill() over fill() when populating
    | a model with arguments in mutation directives. Since GraphQL constrains
    | allowed inputs by design, mass assignment protection is not needed.
    |
    */

    'force_fill' => true,

    /*
    |--------------------------------------------------------------------------
    | Batchload Relations
    |--------------------------------------------------------------------------
    |
    | If set to true, relations marked with directives like @hasMany or @belongsTo
    | will be optimized by combining the queries through the BatchLoader.
    |
    */

    'batchload_relations' => true,

    /*
    |--------------------------------------------------------------------------
    | Shortcut Foreign Key Selection
    |--------------------------------------------------------------------------
    |
    | If set to true, Lighthouse will shortcut queries where the client selects only the
    | foreign key pointing to a related model. Only works if the related model's primary
    | key field is called exactly `id` for every type in your schema.
    |
    */

    'shortcut_foreign_key_selection' => false,

    /*
    |--------------------------------------------------------------------------
    | Non-Null Pagination Results
    |--------------------------------------------------------------------------
    |
    | If set to true, the generated result type of paginated lists will be marked
    | as non-nullable. This is generally more convenient for clients, but will
    | cause validation errors to bubble further up in the result.
    |
    | This setting will be removed and always behave as if it were true in v6.
    |
    */

    'non_null_pagination_results' => false,

    /*
    |--------------------------------------------------------------------------
    | GraphQL Subscriptions
    |--------------------------------------------------------------------------
    |
    | Here you can define GraphQL subscription broadcaster and storage drivers
    | as well their required configuration options.
    |
    */

    'subscriptions' => [
        /*
         * Determines if broadcasts should be queued by default.
         */
        'queue_broadcasts' => false,

        /*
         * Determines the queue to use for broadcasting queue jobs.
         */
        'broadcasts_queue_name' => null,

        /*
         * Default subscription storage.
         *
         * Any Laravel supported cache driver options are available here.
         */
        'storage' => 'redis',

        /*
         * Default subscription storage time to live in seconds.
         *
         * Indicates how long a subscription can be active before it's automatically removed from storage.
         * Setting this to `null` means the subscriptions are stored forever. This may cause
         * stale subscriptions to linger indefinitely in case cleanup fails for any reason.
         */
        'storage_ttl' => 6 * 60 * 60, // 6 hours in seconds

        /*
         * Default subscription broadcaster.
         */
        'broadcaster' => 'pusher',

        /*
         * Subscription broadcasting drivers with config options.
         */
        'broadcasters' => [
            'log' => [
                'driver' => 'log',
            ],

            'pusher' => [
                'driver' => 'pusher',
                'routes' => Nuwave\Lighthouse\Subscriptions\SubscriptionRouter::class.'@pusher',
                'connection' => 'pusher',
            ],

            'echo' => [
                'driver' => 'echo',
                'routes' => Nuwave\Lighthouse\Subscriptions\SubscriptionRouter::class.'@echoRoutes',
                'connection' => 'lighthouse',
            ],
        ],

        /*
         * Controls the format of the extensions response.
         * Allowed values: 1, 2
         */
        'version' => 2,

        /*
         * Should the subscriptions extension be excluded when the response has no subscription channel?
         * This optimizes performance by sending less data, but clients must anticipate this appropriately.
         * Will default to true in v6 and be removed in v7.
         */
        'exclude_empty' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defer
    |--------------------------------------------------------------------------
    |
    | Configuration for the experimental @defer directive support.
    |
    */

    'defer' => [
        /*
         * Maximum number of nested fields that can be deferred in one query.
         * Once reached, remaining fields will be resolved synchronously.
         * 0 means unlimited.
         */
        'max_nested_fields' => 0,

        /*
         * Maximum execution time for deferred queries in milliseconds.
         * Once reached, remaining fields will be resolved synchronously.
         * 0 means unlimited.
         */
        'max_execution_ms' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Apollo Federation
    |--------------------------------------------------------------------------
    |
    | Lighthouse can act as a federated service: https://www.apollographql.com/docs/federation/federation-spec.
    |
    */

    'federation' => [
        /*
         * Location of resolver classes when resolving the `_entities` field.
         */
        'entities_resolver_namespace' => 'App\\GraphQL\\Entities',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    |
    | Configuration for tracing support.
    |
    */

    'tracing' => [
        /*
         * Driver used for tracing.
         *
         * Accepts the fully qualified class name of a class that implements Nuwave\Lighthouse\Tracing\Tracing.
         * Lighthouse provides:
         * - Nuwave\Lighthouse\Tracing\ApolloTracing\ApolloTracing::class
         * - Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class
         *
         * In Lighthouse v7 the default will be changed to 'Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class'.
         */
        'driver' => Nuwave\Lighthouse\Tracing\FederatedTracing\FederatedTracing::class,
    ],

];
