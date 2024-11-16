<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\BadRequestHttpException;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;

class VerifySubscriberEmail
{
    use StripeTrait;

    /**
     * @param  array<string, string>  $args
     *
     * @throws IncompletePayment
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new BadRequestHttpException();
        }

        try {
            /** @var string $email */
            $email = decrypt($args['token']);
        } catch (DecryptException) {
            return false;
        }

        /** @var Subscriber $subscriber */
        $subscriber = auth()->user();

        if ($subscriber->email !== $email) {
            return false;
        }

        $subscriber->update(['verified_at' => now()]);

        $subscription = $tenant->owner->subscription();

        if ($subscription === null) {
            return true;
        }

        $key = sprintf('subscriber-pending-subscription-%d', $subscriber->id);

        $priceId = Cache::pull($key);

        $prices = [
            tenant('stripe_monthly_price_id'),
            tenant('stripe_yearly_price_id'),
        ];

        if (!is_string($priceId) || !in_array($priceId, $prices, true)) {
            return true;
        }

        /** @var TenantSubscriber|null $tenantSubscriber */
        $tenantSubscriber = TenantSubscriber::find($subscriber->id);

        if ($tenantSubscriber === null) {
            return true;
        }

        $this->wrapCashierConfigForSubscriber(function () use ($subscription, $tenantSubscriber, $priceId) {
            $plan = Str::before($subscription->stripe_price ?: '', '-');

            $key = sprintf('billing.fee.%s', $plan ?: 'free');

            $fee = config($key);

            if (!is_numeric($fee)) {
                $fee = 0;
            }

            $tenantSubscriber->newSubscription('default')
                ->price($priceId)
                ->create(null, [], [
                    'application_fee_percent' => max($fee, 0),
                ]);

            $tenantSubscriber->update([
                'first_paid_at' => $tenantSubscriber->first_paid_at ?: now(),
                // 'paid_up_source' => '',
            ]);
        });

        return true;
    }
}
