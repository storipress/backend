<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BasicAuthenticate;
use App\Http\Middleware\HttpRawLogMiddleware;
use App\Http\Middleware\StartSession;
use App\Http\Middleware\ThrottleRequestsWithAvailableInInfo;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Session\Middleware\AuthenticateSession;

final class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string>
     */
    protected $middleware = [
        // HttpRawLogMiddleware::class,
        SecureHeadersMiddleware::class,
        TrustProxies::class,
        HandleCors::class,
        // StartSession::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'api' => [
            'throttle:3600,5',
        ],

        'web' => [
            // EncryptCookies::class,
            // AuthenticateSession::class,
            // VerifyCsrfToken::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string>
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => BasicAuthenticate::class,
        'throttle' => ThrottleRequestsWithAvailableInInfo::class,
    ];
}
