<?php

namespace App\GraphQL\Mutations\Account;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Hash;

final class ChangeAccountPassword
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (! Hash::check($args['current'], $user->password)) {
            return false;
        }

        $updated = $user->update(['password' => Hash::make($args['future'])]);

        UserActivity::log(
            name: 'account.password.change',
        );

        return $updated;
    }
}
