<?php

namespace App\GraphQL\Mutations\Auth;

use App\Models\AccessToken;

abstract class Auth
{
    /**
     * Get the token array structure.
     *
     * @return array{
     *     access_token: string,
     *     token_type: 'bearer',
     *     expires_in: int,
     *     user_id: string,
     * }
     */
    protected function responseWithToken(AccessToken $token): array
    {
        return [
            'access_token' => $token->token,
            'token_type' => 'bearer',
            'expires_in' => (int) $token->expires_at->timestamp,
            'user_id' => $token->tokenable_id,
        ];
    }
}
