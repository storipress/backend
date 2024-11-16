<?php

namespace App\GraphQL\Mutations\User;

use App\Events\Entity\Tenant\UserRoleChanged;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Exceptions\QuotaExceededHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use App\Models\UserStatus;
use App\Notifications\UserRoleChangedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Webmozart\Assert\Assert;

final class ChangeUserRole extends Mutation
{
    /**
     * @param  array<string, string>  $args
     *
     * @throws SubscriptionUpdateFailure
     */
    public function __invoke($_, array $args): User
    {
        $this->authorize('write', User::class);

        /** @var Tenant $tenant */
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $target = User::find($args['id']);

        if ($target === null) {
            throw new NotFoundHttpException();
        }

        /** @var User $manipulator */
        $manipulator = User::find(auth()->user()?->getAuthIdentifier());

        if ($manipulator->getKey() !== $target->getKey()) {
            // change higher user role is not allowed
            if (! $manipulator->isLevelHigherThan($target)) {
                throw new BadRequestHttpException();
            }
        } else {
            // owner can not change self role
            if ($manipulator->role === 'owner') {
                throw new BadRequestHttpException();
            }
        }

        $role = find_role($args['role_id']);

        $own = find_role($manipulator->role);

        // target role level is higher than self is not allowed
        if ($role->level >= $own->level) {
            throw new BadRequestHttpException();
        }

        $subscription = $tenant->owner->subscription();

        if ($subscription === null) {
            throw new BadRequestHttpException();
        }

        $used = $this->used($tenant);

        if ($subscription->name === 'appsumo') {
            $quota = $subscription->quantity;

            if (empty($quota)) {
                $key = sprintf('billing.quota.seats.%s', $subscription->stripe_price);

                $quota = config($key);
            }

            if ($used >= $quota) {
                throw new QuotaExceededHttpException();
            }
        } elseif ($subscription->name === 'default') {
            $interval = Str::afterLast($subscription->stripe_price ?: '', '-');

            if ($interval === 'yearly') {
                if (in_array($target->role, ['contributor', 'author']) && in_array($role->name, ['editor', 'admin'])) {
                    if ($used >= $subscription->quantity) {
                        //$subscription->incrementQuantity(1);
                    }
                } elseif (in_array($target->role, ['editor', 'admin']) && in_array($role->name, ['contributor', 'author'])) {
                    //$subscription->decrementQuantity(1);
                }
            }
        } else {
            throw new BadRequestHttpException();
        }

        $origin = $target->role;

        $target->update(['role' => $role->name]);

        UserRoleChanged::dispatch($tenant->id, $target->id, $origin);

        UserStatus::withoutEagerLoads()
            ->where('tenant_id', '=', $tenant->id)
            ->where('user_id', '=', $target->id)
            ->update(['role' => $role->name]);

        UserActivity::log(
            name: 'team.role.change',
            subject: $target,
            data: [
                'old' => $origin,
                'new' => $role->name,
            ],
        );

        $target->parent?->notify(
            new UserRoleChangedNotification(
                $tenant->id,
                $target->id,
                $role->name,
            ),
        );

        return $target;
    }

    protected function used(Tenant $tenant): int
    {
        $ids = [];

        foreach ($tenant->owner->publications as $publication) {
            $ids[] = $publication->run(function () {
                return User::withoutEagerLoads()
                    ->whereIn('role', ['owner', 'admin', 'editor'])
                    ->pluck('id')
                    ->toArray();
            });
        }

        return count(array_values(array_unique(Arr::flatten($ids))));
    }
}
