<?php

namespace App\Http\Middleware;

use App\Exceptions\BadRequestHttpException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

final class CatchDefinitionException
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (DefinitionException $e) {
            Log::debug($e->getMessage(), debug_backtrace(limit: 10));

            throw new BadRequestHttpException();
        }
    }
}
