<?php

namespace App\Providers;

use App\Packages\Postmark\PostmarkTransport;
use CraigPaul\Mail\PostmarkServiceProvider as BaseServiceProvider;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\Factory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class PostmarkServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        parent::boot();

        $this->app['mail.manager']->extend('postmark', function () {
            $config = $this->app->make('config');

            return new PostmarkTransport(
                $this->app->make(Factory::class),
                $config->get('mail.mailers.postmark.message_stream_id'),
                $config->get('services.postmark.options', []),
                $config->get('services.postmark.token'),
            );
        });

        $this->app['mail.manager']->extend('postmark+api', function ($config) {
            $factory = new PostmarkTransportFactory();

            $options = isset($config['message_stream_id'])
                ? ['message_stream' => $config['message_stream_id']]
                : [];

            return $factory->create(new Dsn(
                'postmark+api',
                'default',
                $config['token'] ?? $this->app['config']->get('services.postmark.token'),
                null,
                null,
                $options,
            ));
        });
    }
}
