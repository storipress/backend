<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\Tenants;
use App\Listeners\BootstrapTenancy;
use App\Listeners\GenerateTenantSecretKeys;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Resolvers\PathTenantResolver;
use Stancl\Tenancy\TenancyServiceProvider as BaseServiceProvider;

final class TenancyServiceProvider extends BaseServiceProvider
{
    /**
     * Load the given routes file if routes are not already cached.
     *
     * @param  string  $path
     */
    protected function loadRoutesFrom($path): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        PathTenantResolver::$tenantParameterName = 'client';

        InitializeTenancyByRequestData::$queryParameter = 'client_id';

        $this->bootEvents();

        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    /**
     * Boot events and listeners.
     */
    protected function bootEvents(): void
    {
        /**
         * @var string $event
         */
        foreach ($this->events() as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Events and its listeners.
     *
     * @return array<array<string|JobPipeline>>
     */
    public function events(): array
    {
        $testing = $this->app->environment('testing');

        return [
            // Tenant events
            Events\CreatingTenant::class => [
                GenerateTenantSecretKeys::class,
            ],

            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Tenants\Database\CreateDefaultTenantBouncers::class,
                    Tenants\Database\CreateDefaultIntegrations::class,
                    Tenants\Database\CreateDefaultDesigns::class,
                    Tenants\Database\CreateDefaultStages::class,
                    // Tenants\CreateStoripressHelperAccount::class,
                    Tenants\CreateOwnerAccount::class,
                    Tenants\ImportDefaultLayouts::class,
                    Tenants\ImportTutorialContent::class,
                    Tenants\CreateDefaultPages::class,
                    Tenants\EnableStoripressAppDomain::class,
                    Tenants\InviteUsers::class,
                    Tenants\GenerateStaticSite::class,

                    // Your own jobs to prepare the tenant.
                    // Provision API keys, create S3 buckets, anything you want!
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(true),
            ],

            Events\SavingTenant::class => [
                //
            ],

            Events\TenantSaved::class => [
                //
            ],

            Events\UpdatingTenant::class => [
                //
            ],

            Events\TenantUpdated::class => [
                //
            ],

            Events\DeletingTenant::class => [
                //
            ],

            Events\TenantDeleted::class => [
                //
            ],

            // Database events
            Events\DatabaseCreated::class => [
                //
            ],

            Events\DatabaseMigrated::class => [
                //
            ],

            Events\DatabaseSeeded::class => [
                //
            ],

            Events\DatabaseRolledBack::class => [
                //
            ],

            Events\DatabaseDeleted::class => [
                //
            ],

            // Tenancy events
            Events\InitializingTenancy::class => [
                //
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
                BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [
                //
            ],

            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [
                //
            ],

            Events\TenancyBootstrapped::class => [
                //
            ],

            Events\RevertingToCentralContext::class => [
                //
            ],

            Events\RevertedToCentralContext::class => [
                //
            ],
        ];
    }

    /**
     * Define the "tenant api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapRoutes(): void
    {
        Route::middleware(['api', InitializeTenancyByPath::class])
            ->prefix('/client/{client}')
            ->group(base_path('routes/tenant.php'));
    }

    /**
     * Adjust the tenancy middleware to highest priority.
     */
    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByPath::class,
        ];

        /** @var Application $app */
        $app = $this->app;

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
