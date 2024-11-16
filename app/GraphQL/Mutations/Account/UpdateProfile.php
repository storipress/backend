<?php

namespace App\GraphQL\Mutations\Account;

use App\Enums\User\Gender;
use App\Events\Entity\Account\AvatarRemoved;
use App\Events\Entity\User\UserUpdated;
use App\Exceptions\AccessDeniedHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Arr;
use stdClass;

class UpdateProfile extends Mutation
{
    /**
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     slug?: string,
     *     avatar?: string|null,
     *     phone_number?: string|null,
     *     gender?: Gender|null,
     *     birthday?: string|null,
     *     location?: string|null,
     *     bio?: string|null,
     *     job_title?: string|null,
     *     contact_email?: string|null,
     *     website?: string|null,
     *     socials?: stdClass|array<int, mixed>|null,
     *     data?: stdClass|array<int, mixed>|null,
     * }  $args
     */
    public function __invoke($_, array $args): User
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new AccessDeniedHttpException();
        }

        $attributes = Arr::except($args, ['avatar']);

        $origin = $user->only(array_keys($attributes));

        $user->update($attributes);

        $changes = $user->getChanges();

        UserUpdated::dispatch($user->id, $changes);

        if (! (empty($origin) && empty($attributes))) {
            UserActivity::log(
                name: 'account.profile.update',
                data: [
                    'old' => $origin,
                    'new' => $attributes,
                ],
            );
        }

        if (array_key_exists('avatar', $args) && empty($args['avatar'])) {
            $user->avatar()->delete();

            AvatarRemoved::dispatch($user->id);

            UserActivity::log(
                name: 'account.avatar.remove',
            );
        }

        return $user->refresh();
    }
}
