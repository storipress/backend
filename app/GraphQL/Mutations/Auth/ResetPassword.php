<?php

namespace App\GraphQL\Mutations\Auth;

use App\Models\PasswordReset;
use App\Models\UserActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ResetPassword
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): bool
    {
        [
            'email' => $email,
            'token' => $token,
            'expire_on' => $expire_on,
            'signature' => $signature,
            'password' => $password,
        ] = $args;

        $email = Str::lower($email);

        $hmac = hmac(compact('email', 'token', 'expire_on'));

        if (! hash_equals($hmac, $signature)) {
            return false;
        }

        if (Carbon::createFromTimestampUTC($expire_on)->isPast()) {
            return false;
        }

        $reset = PasswordReset::whereToken($token)->first();

        if (is_null($reset)) {
            return false;
        }

        $reset->user->update([
            'password' => Hash::make($password),
        ]);

        $deleted = (bool) $reset->delete();

        UserActivity::log(
            name: 'account.password.reset',
            userId: $reset->user->id,
        );

        return $deleted;
    }
}
