<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Billing;

use App\Events\Entity\Subscription\SubscriptionPlanChanged;
use App\Exceptions\Billing\InvalidPromotionCodeException;
use App\Exceptions\Billing\SubscriptionExistsException;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\DB;
use Segment\Segment;

final readonly class ApplyDealFuelCode
{
    /**
     * @param  array{
     *     code: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): bool
    {
        tenancy()->end();

        $user = auth()->user();

        if (! ($user instanceof User)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }

        $exists = DB::table('subscriptions')
            ->where('user_id', '=', $user->id)
            ->whereNot('stripe_id', 'LIKE', 'dealfuel-%')
            ->exists();

        if ($exists) {
            throw new SubscriptionExistsException();
        }

        if (empty($code = $args['code'])) {
            throw new InvalidPromotionCodeException();
        }

        $key = sprintf('billing.dealfuel.%s', $code);

        $tier = config($key);

        if (empty($tier) || ! is_string($tier)) {
            throw new InvalidPromotionCodeException();
        }

        $stripeId = sprintf('dealfuel-%s', $code);

        $used = DB::table('subscriptions')
            ->where('stripe_id', '=', $stripeId)
            ->exists();

        if ($used) {
            throw new InvalidPromotionCodeException();
        }

        $origin = $user->subscription();

        if ($origin) {
            if ($origin->stripe_price === $tier) {
                throw new InvalidPromotionCodeException();
            }

            if ($origin->stripe_price > $tier) {
                throw new InvalidPromotionCodeException();
            }

            $origin->update([
                'stripe_status' => 'canceled',
                'ends_at' => now(),
            ]);
        }

        $quantity = match ($tier) {
            'storipress_bf_tier1' => 1,
            'storipress_bf_tier2' => 3,
            'storipress_bf_tier3' => 8,
            default => null,
        };

        $subscription = $user->subscriptions()->create([
            'name' => 'appsumo',
            'stripe_id' => $stripeId,
            'stripe_status' => 'active',
            'stripe_price' => $tier,
            'quantity' => $quantity,
        ]);

        SubscriptionPlanChanged::dispatch($user->id, $tier);

        if ($origin === null) {
            $event = 'user_subscription_created';

            $name = 'billing.subscription.create';
        } else {
            $event = 'user_subscription_upgraded';

            $name = 'billing.subscription.upgrade';
        }

        UserActivity::log(
            name: $name,
            subject: $subscription,
        );

        Segment::track([
            'userId' => (string) $user->id,
            'event' => $event,
            'properties' => [
                'type' => 'dealfuel',
                'subscription_id' => $subscription->getKey(),
                'partner_id' => $stripeId,
                'plan_id' => $tier,
            ],
        ]);

        return true;
    }
}
