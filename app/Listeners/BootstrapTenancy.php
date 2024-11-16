<?php

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Support\Arr;
use Sentry\State\Scope;
use Stancl\Tenancy\Events\TenancyInitialized;
use Storipress\Webflow\Facades\Webflow;
use Storipress\WordPress\Facades\WordPress;

use function Sentry\configureScope;

final class BootstrapTenancy
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(TenancyInitialized $event)
    {
        /** @var Tenant $tenant */
        $tenant = $event->tenancy->tenant;

        configureScope(function (Scope $scope) use ($tenant) {
            $scope->setTag('tenant', $tenant->id);
        });

        $webflowToken = Arr::get($tenant->webflow_data ?: [], 'access_token');

        if (is_not_empty_string($webflowToken)) {
            Webflow::setToken($webflowToken);
        }

        $wordpress = $tenant->wordpress_data ?: [];

        $wordpressUrl = $wordpress['url'] ?? null;

        $wordpressUsername = $wordpress['username'] ?? null;

        $wordpressToken = $wordpress['access_token'] ?? null;

        $isPrettyUrl = $wordpress['is_pretty_url'] ?? false;

        $prefix = $wordpress['prefix'] ?? '';

        if (is_not_empty_string($wordpressUrl)
            && is_not_empty_string($wordpressUsername)
            && is_not_empty_string($wordpressToken)
        ) {
            WordPress::setUrl($wordpressUrl)
                ->setUsername($wordpressUsername)
                ->setPassword($wordpressToken);
        }

        if ($isPrettyUrl) {
            WordPress::prettyUrl();
        }

        if (is_not_empty_string($prefix)) {
            WordPress::setPrefix($prefix);
        }
    }
}
