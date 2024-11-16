<?php

namespace App\GraphQL\Mutations\Site;

use App\Models\Tenant;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

trait StripeTrait
{
    protected function updateStripeProduct(): void
    {
        $stripe = Cashier::stripe([
            'stripe_account' => tenant('stripe_account_id'),
        ]);

        $productId = $this->stripeProductId($stripe);

        $this->setupStripePrice($stripe, $productId, 'monthly');

        $this->setupStripePrice($stripe, $productId, 'yearly');
    }

    protected function stripeProductId(StripeClient $stripe): string
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string|null $productId */
        $productId = $tenant->getAttribute('stripe_product_id');

        if (!empty($productId)) {
            return $productId;
        }

        $product = $stripe->products->create([
            'name' => 'Storipress Subscription',
        ]);

        $tenant->update([
            'stripe_product_id' => $product->id,
        ]);

        return $product->id;
    }

    /**
     * @throws ApiErrorException
     */
    protected function setupStripePrice(StripeClient $stripe, string $productId, string $interval): void
    {
        $key = sprintf('stripe_%s_price_id', $interval);

        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string|null $priceId */
        $priceId = $tenant->getAttribute($key);

        $amount = intval(tenant(sprintf('%s_price', $interval)) * 100);

        if (!empty($priceId)) {
            $price = $stripe->prices->retrieve($priceId);

            if ($price->unit_amount === $amount) {
                return;
            }

            $stripe->prices->update($price->id, ['active' => false]);
        }

        $price = $stripe->prices->create([
            'product' => $productId,
            'currency' => tenant('currency'),
            'unit_amount' => $amount,
            'recurring' => [
                'interval' => Str::remove('ly', $interval),
            ],
        ]);

        $tenant->update([
            $key => $price->id,
        ]);
    }
}
