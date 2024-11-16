<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRequestsWithAvailableInInfo extends ThrottleRequestsWithRedis
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  array<Limit>  $limits
     *
     * @throws ThrottleRequestsException
     */
    protected function handleRequest($request, Closure $next, array $limits): Response
    {
        foreach ($limits as $limit) {
            /** @var string $key */
            $key = $limit->key;

            if ($this->tooManyAttempts($key, $limit->maxAttempts, $limit->decayMinutes)) {
                throw new RateLimitException('api');
            }
        }

        $response = $next($request);

        if (($limit = $request->offsetGet('VDaiKHWoMmkLCBoKJk5dXOCM')) !== null) {
            /** @var array{key:string, maxAttempts:int, decayMinutes:int, remaining: int, availableIn: int} $limit */
            return $this->handleRequestThrottleDirective($response, $limit);
        }

        return $this->handleRequestStandard($response, $limits);
    }

    /**
     * @param  array<Limit>  $limits
     */
    protected function handleRequestStandard(Response $response, array $limits): Response
    {
        $minRemaining = PHP_INT_MAX;

        foreach ($limits as $limit) {
            /** @var string $key */
            $key = $limit->key;

            $remaining = $this->calculateRemainingAttempts($key, $limit->maxAttempts);

            if ($minRemaining >= $remaining) {
                $minRemaining = $remaining;

                $this->addHeaders(
                    $response,
                    $limit->maxAttempts,
                    $minRemaining,
                );
            }
        }

        return $response;
    }

    /**
     * @param  array{key:string, maxAttempts:int, decayMinutes:int, remaining: int, availableIn: int}  $limit
     */
    protected function handleRequestThrottleDirective(Response $response, array $limit): Response
    {
        return $this->addHeaders(
            $response,
            $limit['maxAttempts'],
            $limit['remaining'],
            $limit['remaining'] === 0 ? $limit['availableIn'] : null,
        );
    }
}
