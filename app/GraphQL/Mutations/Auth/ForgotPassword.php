<?php

namespace App\GraphQL\Mutations\Auth;

use App\Mail\UserPasswordResetMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Mail;

final class ForgotPassword
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $email = $args['email'];

        /** @var User|null $user */
        $user = User::whereEmail($email)->first();

        if (is_null($user)) {
            usleep(mt_rand(500000, 1500000));

            return true;
        }

        /** @var PasswordReset $reset */
        $reset = $user->password_resets()->create([
            'token' => unique_token(),
            'created_at' => now(),
            'expired_at' => now()->addDay(),
        ]);

        Mail::to($user->email)->send(new UserPasswordResetMail(
            name: $user->full_name ?: 'there',
            email: $user->email,
            token: $reset->token,
            expire_on: $reset->expired_at,
        ));

        usleep(mt_rand(250000, 500000));

        UserActivity::log(
            name: 'account.password.forgot',
            userId: $user->id,
        );

        return true;
    }
}
