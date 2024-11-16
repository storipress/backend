<?php

namespace App\Console\Commands\Subscriber;

use App\Models\Tenant;
use App\Models\Tenants\Analysis;
use App\Models\Tenants\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Webmozart\Assert\Assert;

class GatherMonthlyMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscribers:gather-monthly-metrics {--date=} {--tenants=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gather daily subscriber metrics like email opens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date');

        $date = is_string($date)
            ? Carbon::parse($date)->toImmutable()
            : now()->toImmutable();

        $from = $date->startOfMonth();

        $to = $date->endOfMonth();

        tenancy()->runForMultiple(
            $this->option('tenants') ?: null, // @phpstan-ignore-line
            function (Tenant $tenant) use ($from, $to) {
                if (!$tenant->initialized) {
                    return;
                }

                $daily = Analysis::whereBetween('date', [$from, $to])
                    ->orderByDesc('date')
                    ->first();

                Assert::isInstanceOf(
                    $daily,
                    Analysis::class,
                    'Missing daily metric data.',
                );

                $active = Subscriber::whereHas('events', function (Builder $query) use ($from, $to) {
                    $query->whereBetween('occurred_at', [$from, $to]);
                })->count();

                Analysis::updateOrCreate([
                    'year' => $from->year,
                    'month' => $from->month,
                ], [
                    'subscribers' => $daily->subscribers,
                    'paid_subscribers' => $daily->paid_subscribers,
                    'active_subscribers' => $active,
                    'revenue' => $daily->revenue,
                ]);
            },
        );

        return self::SUCCESS;
    }
}
