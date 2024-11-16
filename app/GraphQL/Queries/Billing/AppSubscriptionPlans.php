<?php

namespace App\GraphQL\Queries\Billing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Webmozart\Assert\Assert;

class AppSubscriptionPlans
{
    /**
     * @param  array{}  $args
     * @return array<int, mixed>
     *
     * @throws ApiErrorException
     */
    public function __invoke($_, array $args): array
    {
        $plans = [];

        foreach ($this->prices() as $price) {
            Assert::notNull($price->recurring);

            $plans[] = [
                'id' => $price->id,
                'group' => Str::before($price->id, '-'),
                'currency' => $price->currency,
                'price' => $price->unit_amount_decimal,
                ...$price->recurring->toArray(),
            ];
        }

        return $plans;
    }

    /**
     * @return array<int, Price>
     *
     * @throws ApiErrorException
     */
    public function prices(): array
    {
        $tag = config('cache-keys.billing.tag');

        Assert::stringNotEmpty($tag);

        $key = config('cache-keys.billing.prices');

        Assert::stringNotEmpty($key);

        $params = [
            'active' => true,
            'type' => 'recurring',
            'limit' => 100,
        ];

        /** @var array<int, Price> $prices */
        $prices = Cache::tags($tag)->remember(
            $key,
            now()->addHour(),
            fn () => Cashier::stripe()->prices->all($params)->data,
        );

        Assert::allIsInstanceOf($prices, Price::class);

        if (empty($prices)) {
            Cache::tags($tag)->forget($key);
        }

        $prices = array_filter(
            $prices,
            fn (Price $price) => Str::startsWith($price->id, ['blogger-', 'publisher-']) &&
                Str::endsWith($price->id, ['monthly', 'yearly']),
        );

        return array_values($prices);
    }
}
