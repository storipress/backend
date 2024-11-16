<?php

namespace App\GraphQL\Mutations\Auth;

use App\Models\User;
use App\Models\UserActivity;

class CheckEmailExist
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $user = User::whereEmail($args['email'])->first(['id']);

        if ($user === null) {
            return false;
        }

        UserActivity::log(
            name: 'auth.check_email',
            userId: $user->id,
        );

        return true;
    }
}
