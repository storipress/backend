<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BasicAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse|BinaryFileResponse
    {
        if (
            $request->getUser() !== 'storipress' ||
            ! Hash::check($request->getPassword(), '$argon2id$v=19$m=65536,t=16,p=1$eDBzQWlIODY3clRLRldBZA$XTQ/koY2j5AKniH3/B/yfQyCvVQVaQvSOeh68l7ZIUg')
        ) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}
