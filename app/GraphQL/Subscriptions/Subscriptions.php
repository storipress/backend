<?php

namespace App\GraphQL\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

abstract class Subscriptions extends GraphQLSubscription
{
    /**
     * Check if subscriber is allowed to listen to the subscription.
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return true;
    }

    /**
     * Filter which subscribers should receive the subscription.
     *
     * @param  mixed  $root
     */
    public function filter(Subscriber $subscriber, $root): bool
    {
        return true;
    }
}
