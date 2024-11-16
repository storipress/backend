<?php

namespace App\Listeners\Auth;

use App\Events\Auth\SignedIn;
use App\Events\Auth\SignedUp;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class EnableCustomerIoSubscription implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @see https://customer.io/docs/api/track/#operation/identify
     * @see https://customer.io/docs/api/app/#operation/getTopics
     */
    public function handle(SignedUp|SignedIn $event): void
    {
        $app = app('customerio.app');

        $track = app('customerio.track');

        if ($app === null || $track === null) {
            return;
        }

        $user = User::withoutEagerLoads()
            ->withCount('accessTokens')
            ->find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        if ($user->access_tokens_count !== 1) {
            return;
        }

        $topics = $app
            ->get('/subscription_topics')
            ->json('topics.*.identifier');

        if (! is_array($topics) || empty($topics)) {
            return;
        }

        $track->put(
            sprintf('/customers/%d', $user->id),
            [
                'cio_subscription_preferences' => [
                    'topics' => array_fill_keys($topics, true),
                ],
            ],
        );
    }
}
