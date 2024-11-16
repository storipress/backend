<?php

namespace App\Providers;

use App\Jobs\Webflow\WebflowJob;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use URL;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        if (!($this->app->isLocal() || $this->app->runningUnitTests())) {
            URL::forceScheme('https');
        }

        $this->routes(function () {
            Route::namespace($this->namespace)
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::namespace($this->namespace)
                ->prefix('partners')
                ->group(base_path('routes/partner.php'));

            Route::namespace($this->namespace)
                ->prefix('assistants')
                ->group(base_path('routes/assistant.php'));

            Route::namespace($this->namespace)
                ->prefix('webhooks')
                ->group(base_path('routes/webhook.php'));

            Route::namespace($this->namespace)
                ->prefix('/client/{client}/rest/v1')
                ->group(base_path('routes/rest/v1.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // [name, minute limiter, hour limiter, day limiter, guard]
        $rules = [
            ['user-check-email-exist', [20, 3], [83, 1], null, null],
            ['user-sign-in', [20, 3], [83, 1], null, null],
            ['user-sign-up', [5, 3], [83, 1], [191, 1], null],
            ['user-forgot-password', [5, 3], [17, 1], [37, 1], null],
            ['user-impersonate', [20, 3], [83, 1], null, null],
            ['user-change-account-email', [5, 3], [11, 1], [17, 1], 'api'],
            ['subscriber-sign-in', [20, 3], [83, 1], null, null],
            ['subscriber-sign-up', [5, 3], [83, 1], [191, 1], null],
        ];

        $bypass = $this->app->environment(['local', 'testing', 'development']);

        foreach ($rules as [$name, $minute, $hour, $day, $guard]) {
            RateLimiter::for($name, function (Request $request) use ($bypass, $minute, $hour, $day, $guard) {
                if ($bypass) {
                    return new Unlimited();
                }

                $identifier = $guard ? $request->user()?->id : null; // @phpstan-ignore-line

                $key = empty($identifier) ? $request->ip() : $identifier;

                $limiter = [];

                if (!empty($day)) {
                    $limiter[] = Limit::perDay(...$day)->by('day-' . $key);
                }

                if (!empty($hour)) { // @phpstan-ignore-line
                    $limiter[] = Limit::perHour(...$hour)->by('hour-' . $key);
                }

                if (!empty($minute)) { // @phpstan-ignore-line
                    $limiter[] = new Limit('minute-' . $key, ...$minute);
                }

                return $limiter;
            });
        }

        RateLimiter::for('rebuild-all-sites', function () {
            return Limit::perHour(1);
        });

        RateLimiter::for('webflow-api-general', function (WebflowJob $job) {
            $limit = Limit::perMinute(2)->by($job->rateLimitingKey());

            $limit->decayMinutes = 1 / 12; // 5 seconds @phpstan-ignore-line

            return $limit;
        });

        RateLimiter::for('webflow-api-publish', function (WebflowJob $job) {
            return Limit::perMinute(2)->by($job->rateLimitingKey());
        });

        RateLimiter::for('webflow-api-unlimited', function (WebflowJob $job) {
            return Limit::none();
        });
    }
}
