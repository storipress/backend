<?php

namespace App\Console\Commands\Subscriber;

use App\Models\Tenant;
use App\Models\Tenants\Analysis;
use App\Models\Tenants\Subscriber;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Stripe\Exception\ApiErrorException;

class GatherDailyMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscribers:gather-daily-metrics {--date=} {--tenants=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gather daily subscriber metrics like paid subscribers';

    /**
     * The date to be gathered.
     */
    protected CarbonImmutable $date;

    /**
     * Execute the console command.
     *
     *
     * @throws ApiErrorException
     */
    public function handle(): int
    {
        $date = $this->option('date');

        $this->date = is_string($date)
            ? Carbon::parse($date)->toImmutable()
            : now()->toImmutable();

        $tenants = [];

        tenancy()->runForMultiple(
            $this->option('tenants') ?: null, // @phpstan-ignore-line
            function (Tenant $tenant) use (&$tenants) {
                if (!$tenant->initialized) {
                    return;
                }

                $paid = Subscriber::whereHas('subscriptions', function (Builder $query) {
                    $query
                        ->where('name', 'default')
                        ->whereIn('stripe_status', ['active', 'trialing'])
                        ->where('created_at', '<=', $this->date->endOfDay());
                })->count();

                $revenue = 0;

                if (
                    $tenant->stripe_account_id &&
                    $tenant->stripe_monthly_price_id &&
                    $tenant->stripe_yearly_price_id
                ) {
                    $revenue = ($this->countForSubscription($tenant->stripe_monthly_price_id) * intval($tenant->monthly_price)) +
                               ($this->countForSubscription($tenant->stripe_yearly_price_id) * intval($tenant->yearly_price) / 12);
                }

                Analysis::updateOrCreate(['date' => $this->date->toDateString()], [
                    'subscribers' => Subscriber::where('created_at', '<=', $this->date->endOfDay())->count(),
                    'paid_subscribers' => $paid,
                    'revenue' => intval($revenue * 100),
                ]);

                $tenants[] = $tenant->id;
            },
        );

        if (empty($tenants)) {
            return self::SUCCESS;
        }

        $this->call(GatherMonthlyMetrics::class, [
            '--date' => $this->date->toDateString(),
            '--tenants' => $tenants,
        ]);

        return self::SUCCESS;
    }

    /**
     * @throws ApiErrorException
     */
    protected function countForSubscription(string $price): int
    {
        $stripe = Subscriber::stripe();

        if ($stripe === null) {
            return 0;
        }

        $count = 0;

        $subscriptions = $stripe->subscriptions->all([
            'status' => 'active',
            'price' => $price,
            'limit' => 100,
            'created' => [
                'lte' => $this->date->endOfDay()->timestamp,
            ],
        ]);

        do {
            $count += $subscriptions->count();

            $subscriptions = $subscriptions->nextPage();
        } while (!$subscriptions->isEmpty());

        return $count;
    }
}
