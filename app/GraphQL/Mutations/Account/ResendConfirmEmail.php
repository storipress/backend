<?php

namespace App\GraphQL\Mutations\Account;

use App\Exceptions\AccessDeniedHttpException;
use App\Mail\UserEmailVerifyMail;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Mail;

final class ResendConfirmEmail
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (is_null($user)) {
            throw new AccessDeniedHttpException();
        }

        if ($user->verified) {
            return true;
        }

        Mail::to($user->email)->send(
            new UserEmailVerifyMail($user->email),
        );

        UserActivity::log(
            name: 'account.email.request_verification',
        );

        return true;
    }
}
