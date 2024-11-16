<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Account;

use App\Events\Entity\Account\AvatarRemoved;
use App\Exceptions\AccessDeniedHttpException;
use App\Models\User;
use App\Models\UserActivity;

final readonly class RemoveAvatar
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): User
    {
        $user = auth()->user();

        if (!($user instanceof User)) {
            throw new AccessDeniedHttpException();
        }

        $user->avatar()->delete();

        AvatarRemoved::dispatch($user->id);

        UserActivity::log(
            name: 'account.avatar.remove',
        );

        return $user;
    }
}
