<?php

namespace App\GraphQL\Mutations\Auth;

use App\Models\User;
use Webmozart\Assert\Assert;

final class RefreshToken extends Auth
{
    /**
     * @return array<int|string>
     */
    public function __invoke(): array
    {
        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        return $this->responseWithToken($user->access_token);
    }
}
