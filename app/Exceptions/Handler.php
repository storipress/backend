<?php

namespace App\Exceptions;

use Fruitcake\Cors\CorsService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByPathException as TenantNotFound;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * @phpstan-import-type CorsInputOptions from CorsService
 */
final class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->map(
            TenantNotFound::class,
            fn () => new NotFoundHttpException(),
        );

        $this->map(
            TenantCouldNotBeIdentifiedByRequestDataException::class,
            fn () => new NotFoundHttpException(),
        );

        // @see: https://docs.sentry.io/platforms/php/guides/laravel/#install
        $this->reportable(function (Throwable $e) {
            Integration::captureUnhandledException($e);
        });
    }

    public function render($request, Throwable $e): Response
    {
        $response = parent::render($request, $e);

        if (! $response->isServerError()) {
            return $response;
        }

        // add cors headers
        $cors = new CorsService();

        /** @var CorsInputOptions $config */
        $config = config('cors', []);

        $cors->setOptions($config);

        return $cors->addActualRequestHeaders($response, $request);
    }
}
