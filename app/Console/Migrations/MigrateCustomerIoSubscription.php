<?php

namespace App\Console\Migrations;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

class MigrateCustomerIoSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:customer-io-subscription';

    /**
     * Execute the console command.
     *
     * @see https://customer.io/docs/api/track/#operation/identify
     * @see https://customer.io/docs/api/app/#operation/getPersonSubscriptionPreferences
     * @see https://customer.io/docs/api/app/#operation/getTopics
     */
    public function handle(): int
    {
        if (! $this->confirm('This may override customer\'s preference, confirm to run?')) {
            return static::SUCCESS;
        }

        $app = app('customerio.app');

        $track = app('customerio.track');

        if ($app === null || $track === null) {
            return static::FAILURE;
        }

        $topics = $app
            ->get('/subscription_topics')
            ->json('topics.*.identifier');

        // If there is no topics, then there is nothing to do.
        if (! is_array($topics) || empty($topics)) {
            return static::SUCCESS;
        }

        $payload = array_fill_keys($topics, true);

        $users = User::withoutEagerLoads()
            ->whereHas('accessTokens', function (Builder $query) {
                $query->where('name', '!=', 'impersonate');
            })
            ->lazyById();

        foreach ($users as $user) {
            $preference = $app->get(
                sprintf('/customers/%d/subscription_preferences', $user->id),
            );

            // skip if the user is not found
            if ($preference->notFound()) {
                continue;
            }

            // skip if the user is already unsubscribed
            if ($preference->json('customer.unsubscribed')) {
                continue;
            }

            $subscribed = $preference->json('customer.topics.*.subscribed');

            Assert::isArray($subscribed);

            // skip if the user has updated the subscription preference,
            // all topics will be false in the default preference
            if (count(array_filter($subscribed)) !== 0) {
                continue;
            }

            $track->put(
                sprintf('/customers/%d', $user->id),
                [
                    'cio_subscription_preferences' => [
                        'topics' => $payload,
                    ],
                ],
            );
        }

        return static::SUCCESS;
    }
}
