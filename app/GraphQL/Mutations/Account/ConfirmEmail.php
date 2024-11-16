<?php

namespace App\GraphQL\Mutations\Account;

use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Carbon;

final class ConfirmEmail extends Mutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): bool
    {
        [
            'email' => $email,
            'expire_on' => $expire_on,
            'signature' => $signature,
        ] = $args;

        $hmac = hmac(['email' => $email, 'expire_on' => $expire_on]);

        if (!hash_equals($hmac, $signature)) {
            throw new NotFoundHttpException();
        }

        if (Carbon::createFromTimestamp((int) $expire_on)->isPast()) {
            throw new NotFoundHttpException();
        }

        /** @var User $user */
        $user = auth()->user();

        if (!hash_equals($user->email, $email)) {
            throw new NotFoundHttpException();
        }

        if ($user->verified) {
            return true;
        }

        $user->update(['verified_at' => now()]);

        UserActivity::log(
            name: 'account.email.verify',
        );

        return true;
    }
}
