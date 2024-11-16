<?php

namespace App\Http\Controllers\Testing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FakeAppSumoSignUpCode extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->input('token');

        $email = $request->input('email');

        if (empty($token) || empty($email)) {
            return response()->json([
                'ok' => false,
                'message' => 'Missing token or email.',
            ]);
        }

        $ok = Cache::put('appsumo-'.$token, $email);

        return response()->json(['ok' => $ok]);
    }
}
