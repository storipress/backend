<?php

namespace App\Providers;

use App\SDK\Cloudflare\Cloudflare;
use App\SDK\Iframely\Iframely;
use App\SDK\ProseMirror\ProseMirror;
use App\SDK\Unsplash\Unsplash;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use JoliCode\Slack\ClientFactory;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha384;
use Lcobucci\JWT\Signer\Key\InMemory;
use Postmark\PostmarkAdminClient;
use Segment\Segment;
use Storipress\WordPress\Facades\WordPress;
use Webmozart\Assert\Assert;

class ThirdPartyServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->http();

        $this->http2();

        $this->cloudflare();

        $this->customerio();

        $this->iframely();

        $this->jwt();

        $this->postmark();

        $this->prosemirror();

        $this->segment();

        $this->sendgrid();

        $this->slack();

        $this->unsplash();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->wordpress();
    }

    /**
     * Http request.
     */
    protected function http(): void
    {
        $this->app->bind('http', function (Application $app) {
            $release = $app['config']->get('sentry.release');

            $userAgent = sprintf(
                'storipress/%s',
                is_string($release) ? $release : '2023-02-13',
            );

            return Http::connectTimeout(2)
                ->timeout(5)
                ->retry(1, 1000)
                ->acceptJson()
                ->asJson()
                ->withUserAgent($userAgent);
        });
    }

    /**
     * Http request.
     */
    protected function http2(): void
    {
        $this->app->bind('http2', function (Application $app) {
            $release = $app['config']->get('sentry.release');

            $userAgent = sprintf(
                'Storipress/%s  (https://storipress.com/; kevin@storipress.com)',
                is_string($release) ? $release : '2023-07-25',
            );

            return Http::connectTimeout(3)
                ->timeout(7)
                ->acceptJson()
                ->asJson()
                ->withUserAgent($userAgent)
                ->maxRedirects(2)
                ->withOptions([
                    'allow_redirects' => [
                        'referer' => true,
                        'track_redirects' => true,
                    ],
                ]);
        });
    }

    /**
     * https://api.cloudflare.com
     */
    protected function cloudflare(): void
    {
        $this->app->singleton('cloudflare', function (Application $app) {
            $key = $app['config']->get('services.cloudflare.api_key');

            Assert::stringNotEmpty($key);

            $accountId = $app['config']->get('services.cloudflare.account_id');

            Assert::stringNotEmpty($accountId);

            return new Cloudflare($key, $accountId);
        });
    }

    /**
     * https://customer.io/docs/api/track/?region=eu
     * https://customer.io/docs/api/app/?region=eu
     */
    protected function customerio(): void
    {
        $this->app->singleton('customerio.track', function (Application $app) {
            $site = $app['config']->get('services.customerio.site_id');

            $track = $app['config']->get('services.customerio.track_key');

            if (!is_not_empty_string($site) || !is_not_empty_string($track)) {
                return null;
            }

            return $app['http']
                ->withBasicAuth($site, $track)
                ->baseUrl('https://track-eu.customer.io/api/v1/');
        });

        $this->app->singleton('customerio.app', function (Application $app) {
            $token = $app['config']->get('services.customerio.app_key');

            if (!is_not_empty_string($token)) {
                return null;
            }

            return $app['http']
                ->withToken($token)
                ->baseUrl('https://api-eu.customer.io/v1/');
        });
    }

    /**
     * https://iframely.com/docs/iframely-api
     */
    protected function iframely(): void
    {
        $this->app->singleton('iframely', function (Application $app) {
            $key = $app['config']->get('services.iframely.api_key');

            Assert::stringNotEmpty($key);

            return new Iframely($key);
        });
    }

    /**
     * jwt
     */
    protected function jwt(): void
    {
        $this->app->singleton('jwt', function (Application $app) {
            return Configuration::forAsymmetricSigner(
                new Sha384(),
                InMemory::base64Encoded($app['config']->get('services.jwt.private_key')), // @phpstan-ignore-line
                InMemory::base64Encoded($app['config']->get('services.jwt.public_key')), // @phpstan-ignore-line
            );
        });

        $this->app->singleton('jwt.builder', function (Application $app) {
            $url = $app['config']->get('app.url');

            $url = filter_var($url, FILTER_VALIDATE_URL) ?: 'https://api.stori.press';

            return $app['jwt']
                ->builder()
                ->issuedBy($url)
                ->identifiedBy(Str::uuid()->toString());
        });

        $this->app->singleton('jwt.parser', function (Application $app) {
            return $app['jwt']->parser();
        });
    }

    /**
     * https://github.com/wildbit/postmark-php
     */
    protected function postmark(): void
    {
        $this->app->singleton('postmark.account', function (Application $app) {
            $token = $app['config']->get('services.postmark.account_token');

            Assert::stringNotEmpty($token);

            return new PostmarkAdminClient($token);
        });
    }

    protected function prosemirror(): void
    {
        $this->app->singleton('prosemirror', function () {
            return new ProseMirror();
        });
    }

    /**
     * https://segment.com/docs/connections/sources/catalog/libraries/server/php/
     */
    protected function segment(): void
    {
        $config = $this->app->get('config');

        Assert::isInstanceOf($config, Repository::class);

        $key = $config->get('services.segment.write_key');

        if (!is_not_empty_string($key)) {
            return;
        }

        Segment::init($key);
    }

    /**
     * https://docs.sendgrid.com/api-reference/how-to-use-the-sendgrid-v3-api/authentication
     */
    protected function sendgrid(): void
    {
        $this->app->singleton('sendgrid', function (Application $app) {
            $token = $app['config']->get('services.sendgrid.api_key');

            Assert::stringNotEmpty($token);

            return $app['http']
                ->withToken($token)
                ->baseUrl('https://api.sendgrid.com/v3/');
        });
    }

    /**
     * https://github.com/jolicode/slack-php-api
     */
    protected function slack(): void
    {
        $this->app->singleton('slack', function (Application $app) {
            $token = $app['config']->get('services.slack.token');

            Assert::stringNotEmpty($token);

            return ClientFactory::create($token);
        });
    }

    /**
     * https://unsplash.com/documentation
     */
    protected function unsplash(): void
    {
        $this->app->singleton('unsplash', function (Application $app) {
            $key = $app['config']->get('services.unsplash.access_key');

            Assert::stringNotEmpty($key);

            return new Unsplash($key);
        });
    }

    protected function wordpress(): void
    {
        WordPress::withUserAgent('Storipress/2023-12-01 (https://storipress.com/; kevin@storipress.com)');
    }
}
