<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Segment\Segment;

final class RevokeSubscriberSubscription
{
    /**
     * @param  array{
     *     id: string,
     * }  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $authenticatable = auth()->user();

        if (! ($authenticatable instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $user = TenantUser::find($authenticatable->id);

        if (! ($user instanceof TenantUser)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        if (! in_array($user->role, ['owner', 'admin'], true)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $subscriber = Subscriber::find($args['id']);

        if (! ($subscriber instanceof Subscriber)) {
            throw new HttpException(ErrorCode::MEMBER_NOT_FOUND);
        }

        $stripeSubscription = $subscriber->subscription();

        if ($stripeSubscription !== null && $stripeSubscription->active()) {
            throw new HttpException(ErrorCode::MEMBER_STRIPE_SUBSCRIPTION_IRREVOCABLE);
        }

        $subscription = $subscriber->subscription('manual');

        if ($subscription === null || ! $subscription->active()) {
            throw new HttpException(ErrorCode::MEMBER_MANUAL_SUBSCRIPTION_NOT_FOUND);
        }

        $subscription->markAsCanceled();

        Segment::track([
            'userId' => (string) $user->id,
            'event' => 'tenant_member_subscription_revoked',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_member_uid' => $subscriber->id,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        return $subscription->ended();
    }
}
