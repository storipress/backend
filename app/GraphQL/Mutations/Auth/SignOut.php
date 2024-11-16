<?php

namespace App\GraphQL\Mutations\Auth;

use App\Models\User;
use App\Models\UserActivity;
use Exception;
use Webmozart\Assert\Assert;

final class SignOut
{
    /**
     * @throws Exception
     */
    public function __invoke(): bool
    {
        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        $user->access_token->update([
            'expires_at' => now(),
        ]);

        UserActivity::log(
            name: 'auth.sign_out',
        );

        return true;
    }
}
