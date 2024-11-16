<?php

namespace App\GraphQL\Mutations\Account;

use App\Events\Entity\Account\AccountDeleted;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DeleteAccount
{
    /**
     * @param  array{
     *     password: string,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $user = auth()->user();

        if (!($user instanceof User)) {
            return false;
        }

        if (!Hash::check($args['password'], $user->password)) {
            return false;
        }

        $email = sprintf(
            'trashed+%s@storipress.com',
            Str::lower(Str::random(12)),
        );

        $user->update([
            'email' => $email,
            'password' => Hash::make(Str::random()),
        ]);

        AccountDeleted::dispatch($user->id);

        return true;
    }
}
