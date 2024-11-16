<?php

namespace App\GraphQL\Mutations\Site;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Subscription\Setup;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class UpdateSubscription extends Mutation
{
    use StripeTrait;

    /**
     * @var array<string, bool>
     */
    protected array $attributes = [
        'email' => true,
        'accent_color' => true,
        'currency' => true,
        'monthly_price' => true,
        'yearly_price' => true,
    ];

    /**
     * @param  array{
     *     subscription: bool,
     *     newsletter: bool,
     *     email?: string,
     *     accent_color?: string,
     *     currency?: string,
     *     monthly_price?: string,
     *     yearly_price?: string,
     * }  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        $this->authorize('write', Tenant::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $paid = (bool) $args['subscription'];

        $attributes = [
            'subscription_setup' => $paid
                ? Setup::waitConnectStripe()
                : Setup::waitImport(),
            'subscription' => $paid,
            'newsletter' => $args['newsletter'],
        ];

        foreach (array_intersect_key($args, $this->attributes) as $key => $field) {
            $attributes[$key] = empty($field) ? null : $field;
        }

        if (is_not_empty_string($attributes['email'] ?? '')) {
            $attributes['email'] = Str::lower($attributes['email']);
        }

        $origin = $tenant->only(array_keys($attributes));

        $tenant->update($attributes);

        if ($paid && ! empty($tenant->stripe_account_id)) {
            $this->updateStripeProduct();
        }

        UserActivity::log(
            name: 'member.update',
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        $builder = new ReleaseEventsBuilder();

        $builder->handle('subscription:update');

        return $tenant;
    }
}
