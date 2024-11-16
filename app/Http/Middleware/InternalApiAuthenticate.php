<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InternalApiAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $key = 'sp-iak';

        $token = $request->header($key);

        if (! is_string($token)) {
            $token = $request->input($key);
        }

        if (! is_string($token)) {
            throw new AccessDeniedHttpException();
        }

        /** @var string $secret */
        $secret = config('services.storipress.api_key');

        if (! hash_equals($secret, $token)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
