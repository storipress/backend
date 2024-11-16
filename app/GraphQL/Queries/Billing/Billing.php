<?php

namespace App\GraphQL\Queries\Billing;

use App\Enums\Credit\State as CreditState;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Laravel\Cashier\Tax;
use Stripe\Discount;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeObject;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class Billing
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     *
     * @throws InvalidRequestException
     */
    public function __invoke($_, array $args): array
    {
        /** @var User $user */
        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        $user->load('credits');

        $user->loadCount('publications');

        $subscribed = $user->subscribed();

        $subscription = $user->subscription();

        Assert::nullOrIsInstanceOf($subscription, Subscription::class);

        $subscriptionItem = $subscription?->items->first();

        Assert::nullOrIsInstanceOf($subscriptionItem, SubscriptionItem::class);

        $appsumo = $subscription?->name === 'appsumo';

        $viededingue = $appsumo && Str::startsWith($subscription->stripe_id ?: '', 'viededingue-');

        $dealfuel = $appsumo && Str::startsWith($subscription->stripe_id ?: '', 'dealfuel-');

        $prophet = $appsumo && $subscription->stripe_price === 'prophet';

        $invoice = $subscription?->name === 'default'
            ? $user->upcomingInvoice()
            : null;

        try {
            $discounts = $invoice?->discounts();
        } catch (InvalidRequestException $e) {
            if (Str::contains($e->getMessage(), 'No upcoming invoices for customer')) {
                $invoice = $discounts = null;
            } else {
                captureException($e);

                throw $e;
            }
        }

        // stripe invoice must load after discounts was loaded
        $stripeInvoice = $invoice?->asStripeInvoice();

        $plan = $subscribed ? Str::before($subscription?->stripe_price ?: '', '-') : null;

        return [
            'id' => $user->getKey(),

            // payment method info
            'has_pm' => $user->hasDefaultPaymentMethod(),
            'pm_type' => $user->pm_type,
            'pm_last_four' => $user->pm_last_four,

            // subscription info
            'subscribed' => $subscribed,
            'source' => $subscribed ? ($appsumo ? 'appsumo' : 'stripe') : null,
            'plan' => $plan,
            'plan_id' => $subscribed ? ($appsumo ? $subscriptionItem?->stripe_id : $subscription?->stripe_price) : null,
            'referer' => $subscribed ? ($prophet ? 'prophet' : ($viededingue ? 'viededingue' : ($dealfuel ? 'dealfuel' : ($appsumo ? 'appsumo' : 'stripe')))) : null,
            'interval' => $subscribed
                ? ($appsumo
                    ? 'lifetime'
                    : Str::afterLast($subscription?->stripe_price ?: '', '-')
                )
                : null,
            'quantity' => $subscription?->quantity,
            'has_historical_subscriptions' => $user->subscriptions()->count() > 0,
            'has_prophet' => $prophet ?: $user->subscriptions()->where('stripe_price', '=', 'prophet')->exists(),

            // next invoice info
            'credit_balance' => $credits = $user->credits()->where('state', '=', CreditState::available())->sum('amount'),
            'next_pm_date' => $invoice?->date(),
            'next_pm_subtotal' => $stripeInvoice?->subtotal,
            'next_pm_discounts' => array_map(function (StripeObject $item) {
                /** @var Discount $discount */
                $discount = $item['discount'];

                Assert::isInstanceOf($discount, Discount::class);

                Assert::integer($item['amount']);

                return [
                    'name' => $discount->coupon->name,
                    'amount' => $item['amount'],
                    'amount_off' => $discount->coupon->amount_off,
                    'percent_off' => $discount->coupon->percent_off,
                ];
            }, $discounts ? ($stripeInvoice?->total_discount_amounts ?: []) : []),
            'next_pm_tax' => $stripeInvoice?->tax,
            'next_pm_taxes' => array_map(fn (Tax $tax) => [
                'amount' => $tax->rawAmount(),
                'name' => $tax->taxRate()->display_name,
                'jurisdiction' => $tax->taxRate()->jurisdiction,
                'percentage' => $tax->taxRate()->percentage,
            ], $invoice?->taxes() ?: []),
            'next_pm_total' => $stripeInvoice ? max($stripeInvoice->total - $credits, 0) : null,
            'discount' => $invoice?->rawDiscount() ?: 0,
            'account_balance' => -$invoice?->rawStartingBalance() ?: 0, // in stripe, negative numbers represent balance, and positive numbers represent debts

            // subscription trial info
            'on_trial' => $user->onTrial(),
            'trial_ends_at' => $user->trialEndsAt(),

            // subscription cancel info
            'canceled' => $subscribed && $subscription?->canceled(),
            'on_grace_period' => $subscribed && $subscription?->onGracePeriod(),
            'ends_at' => $subscribed ? $subscription?->ends_at : null,

            // subscription usage info
            'publications_quota' => $appsumo
                ? $this->appsumoPublicationQuota($plan)
                : (
                    $user->onGenericTrial()
                        ? config('billing.quota.publications.enterprise')
                        : config(sprintf('billing.quota.publications.%s', $plan), 1)
                ),
            'publications_count' => $user->publications_count,
            'seats_in_use' => $this->seatsInUse($user),
        ];
    }

    protected function upcomingInvoice(User $user): ?Invoice
    {
        $tag = config('cache-keys.billing.tag');

        Assert::stringNotEmpty($tag);

        $key = config('cache-keys.billing.invoice');

        Assert::stringNotEmpty($key);

        $invoice = Cache::tags($tag)->remember(
            sprintf($key, (string) $user->id),
            now()->addHour(),
            fn () => $user->upcomingInvoice(),
        );

        Assert::nullOrIsInstanceOf($invoice, Invoice::class);

        return $invoice;
    }

    protected function appsumoPublicationQuota(?string $plan): int
    {
        $enterprise = config('billing.quota.publications.enterprise');

        Assert::positiveInteger($enterprise);

        return match ($plan) {
            'storipress_tier1' => 1,
            'storipress_tier2' => 3,
            'storipress_tier3' => $enterprise,
            'storipress_bf_tier1' => 1,
            'storipress_bf_tier2' => 3,
            'storipress_bf_tier3' => $enterprise,
            default => 1,
        };
    }

    /**
     * Get seats in use.
     */
    protected function seatsInUse(User $user): int
    {
        $ids = $user->publications->map(function (Tenant $tenant) {
            if (! $tenant->initialized) {
                return [];
            }

            return $tenant->run(function () {
                return TenantUser::whereNot('id', 1)
                    ->get()
                    ->filter(
                        fn (TenantUser $user) => in_array($user->role, ['owner', 'admin', 'editor'], true),
                    )
                    ->pluck('id')
                    ->toArray();
            });
        });

        return $ids->flatten()->push($user->id)->unique()->count();
    }
}
