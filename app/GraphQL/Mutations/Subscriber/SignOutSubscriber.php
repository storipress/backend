<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Subscriber;
use Webmozart\Assert\Assert;

class SignOutSubscriber
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $subscriber = auth()->user();

        Assert::isInstanceOf($subscriber, Subscriber::class);

        $subscriber->access_token->update([
            'expires_at' => now(),
        ]);

        return true;
    }
}
