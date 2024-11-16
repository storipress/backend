<?php

namespace App\GraphQL;

use Illuminate\Routing\Router;
use Nuwave\Lighthouse\Subscriptions\SubscriptionController;
use Nuwave\Lighthouse\Subscriptions\SubscriptionRouter as BaseRouter;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

final class SubscriptionRouter extends BaseRouter
{
    /**
     * Register the routes for pusher based subscriptions.
     *
     * @param  Router  $router
     */
    public function pusher($router): void
    {
        $router->middleware([
            'api',
            InitializeTenancyByPath::class,
        ])
            ->post('/client/{client}/graphql/subscriptions/auth')
            ->uses([SubscriptionController::class, 'authorize']);

        $router->middleware([
            'api',
            InitializeTenancyByPath::class,
        ])
            ->post('/client/{client}/graphql/subscriptions/webhook')
            ->uses([SubscriptionController::class, 'webhook']);
    }
}
