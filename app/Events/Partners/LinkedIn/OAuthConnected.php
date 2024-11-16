<?php

namespace App\Events\Partners\LinkedIn;

use App\Resources\Partners\LinkedIn\User;
use Illuminate\Foundation\Events\Dispatchable;

class OAuthConnected
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  string[]  $scopes
     * @return void
     */
    public function __construct(
        public string $token,
        public string $refreshToken,
        public User $user,
        public array $scopes,
        public string $tenantId,
    ) {
        //
    }
}
