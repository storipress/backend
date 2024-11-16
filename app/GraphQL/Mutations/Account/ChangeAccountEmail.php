<?php

namespace App\GraphQL\Mutations\Account;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Mail\UserEmailVerifyMail;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ChangeAccountEmail extends Mutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): User
    {
        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new AccessDeniedHttpException();
        }

        if (!Hash::check($args['password'], $user->password)) {
            throw new BadRequestHttpException();
        }

        $updated = $user->update([
            'email' => Str::lower($args['email']),
            'verified_at' => null,
        ]);

        if (!$updated) {
            throw new InternalServerErrorHttpException();
        }

        $user->refresh();

        Mail::to($user->email)->send(
            new UserEmailVerifyMail($user->email),
        );

        UserActivity::log(
            name: 'account.email.change',
        );

        return $user;
    }
}
