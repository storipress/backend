<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Laravel\Cashier\Subscription;
use RuntimeException;
use Sentry\State\Scope;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class SyncUserSubscriptionSeats extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:subscription:seat:sync {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user subscription quantity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        $owners = User::with('publications')
            ->whereNotNull('stripe_id')
            ->lazyById();

        foreach ($owners as $owner) {
            Assert::isInstanceOf($owner, User::class);

            $subscription = $owner->subscription();

            if (!$owner->subscribed() || !($subscription instanceof Subscription)) {
                continue;
            }

            if ($subscription->name === 'appsumo') {
                continue;
            }

            if ($subscription->stripe_price === null) {
                withScope(function (Scope $scope) use ($owner, $subscription) {
                    $scope->setContext('owner', $owner->toArray());
                    $scope->setContext('subscription', $subscription->toArray());

                    captureException(new RuntimeException('Invalid subscription data.'));
                });

                continue;
            }

            if (!Str::contains($subscription->stripe_price, 'yearly')) {
                continue;
            }

            $pay = [$owner->id];

            tenancy()->runForMultiple(
                $owner->publications,
                function (Tenant $tenant) use ($owner, &$pay) {
                    if (!$tenant->initialized) {
                        return;
                    }

                    $users = TenantUser::get();

                    foreach ($users as $user) {
                        if ($user->id === 1 || $owner->id === $user->id) {
                            continue;
                        }

                        if (!in_array($user->role, ['owner', 'admin', 'editor'], true)) {
                            continue;
                        }

                        $pay[] = $user->id;
                    }
                },
            );

            $pay = array_values(array_unique($pay));

            $shouldPay = count($pay) - $subscription->quantity;

            if ($shouldPay === 0) {
                continue;
            }

            $this->info(
                sprintf(
                    '%s(%s) %s %d quantity for their subscription.',
                    $owner->full_name ?: $owner->id,
                    $owner->email,
                    $shouldPay > 0 ? 'increases' : 'decreases',
                    abs($shouldPay),
                ),
            );

            if ($dryRun) {
                continue;
            }

            try {
                $shouldPay > 0
                    ? $subscription->incrementQuantity($shouldPay)
                    : $subscription->decrementQuantity(abs($shouldPay));
            } catch (SubscriptionUpdateFailure $e) {
                withScope(function (Scope $scope) use ($e, $owner, $subscription, $pay) {
                    $scope->setContext('owner', $owner->toArray());
                    $scope->setContext('subscription', $subscription->toArray());
                    $scope->setContext('pay', ['users' => $pay]);

                    captureException($e);
                });
            }
        }

        return self::SUCCESS;
    }
}
