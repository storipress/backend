<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

final class BuilderAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = 'storipress-internal-api-key';

        $token = $request->header($key);

        if (!is_string($token)) {
            $token = $request->input($key);
        }

        /** @var string $secret */
        $secret = config('services.storipress.api_key');

        if (is_string($token) && $secret && hash_equals($secret, $token) && ($tenant = tenant()) instanceof Tenant) {
            auth()->setUser($tenant->owner);
        }

        return $next($request);
    }
}
