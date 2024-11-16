<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSumoTokenController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->only(['username', 'password']);

        $known = config('services.appsumo');

        if (
            ! is_array($known) ||
            ! is_string($credentials['username'] ?? null) ||
            ! is_string($credentials['password'] ?? null) ||
            ! is_string($known['username'] ?? null) ||
            ! is_string($known['password'] ?? null) ||
            ! hash_equals($known['username'], $credentials['username']) ||
            ! hash_equals($known['password'], $credentials['password'])
        ) {
            return response()->json([], 403, [], JSON_FORCE_OBJECT);
        }

        $now = now()->toImmutable();

        $token = app('jwt.builder')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->addMinutes())
            ->getToken(
                app('jwt')->signer(),
                app('jwt')->signingKey(),
            )
            ->toString();

        return response()->json(['access' => $token]);
    }
}
