<?php

namespace App\Providers;

use App\Authentication\AuthGuard;
use App\Authentication\UserProvider;
use App\Models\CustomDomain;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Design;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\Layout;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\Tag;
use App\Models\Tenants\Template;
use App\Models\Tenants\User;
use App\Policies\ArticlePolicy;
use App\Policies\CustomDomainPolicy;
use App\Policies\CustomFieldPolicy;
use App\Policies\DeskPolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\LayoutPolicy;
use App\Policies\ScraperPolicy;
use App\Policies\StagePolicy;
use App\Policies\SubscriberPolicy;
use App\Policies\TagPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Contracts\Auth\UserProvider as BaseUserProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Webmozart\Assert\Assert;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Article::class => ArticlePolicy::class,
        // Billing
        CustomField::class => CustomFieldPolicy::class,
        Design::class => DeskPolicy::class,
        Desk::class => DeskPolicy::class,
        CustomDomain::class => CustomDomainPolicy::class,
        Integration::class => IntegrationPolicy::class,
        Invitation::class => InvitationPolicy::class,
        Layout::class => LayoutPolicy::class,
        Scraper::class => ScraperPolicy::class,
        Stage::class => StagePolicy::class,
        Subscriber::class => SubscriberPolicy::class,
        Tag::class => TagPolicy::class,
        Template::class => TemplatePolicy::class,
        Tenant::class => TenantPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::provider('storipress', function (Application $app) {
            return $app->make(UserProvider::class);
        });

        Auth::extend('storipress', function (Application $app) {
            $provider = $app['auth']->createUserProvider('storipress');

            Assert::isInstanceOf($provider, BaseUserProvider::class);

            return new AuthGuard($provider, $app['request']);
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
