<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Models\Tenant;
use App\Models\Tenants\ArticleAnalysis;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\SubscriberEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class GatherProphetMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prophet:gather-metrics {--date=} {--monthly} {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monthly = $this->option('monthly');

        $date = is_string($this->option('date'))
            ? Carbon::parse($this->option('date'))->toImmutable()
            : now()->toImmutable();

        $range = [
            $monthly ? $date->startOfMonth() : $date->startOfDay(),
            $monthly ? $date->endOfMonth() : $date->endOfDay(),
        ];

        $tenants = Tenant::withoutEagerLoads()->initialized();

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        runForTenants(
            function (Tenant $tenant) use ($monthly, $date, $range) {
                $lock = sprintf('prophet-metric-%s-%d-%d', $tenant->id, (int) $monthly, $date->getTimestamp());

                if (! Cache::add($lock, true, 5)) {
                    return;
                }

                if (! $tenant->has_prophet) {
                    return;
                }

                $articleRead = SubscriberEvent::where('name', '=', 'article.read')
                    ->whereBetween('occurred_at', [$monthly ? $range[0] : $date->startOfCentury(), $range[1]])
                    ->get();

                $articleUniqueRead = $articleRead
                    ->groupBy('subscriber_id')
                    ->map(function (Collection $collection, int $key) {
                        if ($key === 0) {
                            return $collection
                                ->groupBy('anonymous_id')
                                ->map(function (Collection $collection) {
                                    return $collection->unique('target_id')->count();
                                });
                        }

                        return $collection->unique('target_id')->count();
                    })
                    ->flatten()
                    ->sum();

                $articleAvgScrolled = $articleRead
                    ->groupBy('subscriber_id')
                    ->map(function (Collection $collection, int $key) {
                        $fn = function (Collection $items) {
                            return $items->max('data.percentage');
                        };

                        if ($key === 0) {
                            return $collection
                                ->groupBy('anonymous_id')
                                ->map(function (Collection $collection) use ($fn) {
                                    return $collection->groupBy('target_id')->map($fn);
                                });
                        }

                        return $collection->groupBy('target_id')->map($fn); // @phpstan-ignore-line
                    })
                    ->flatten()
                    ->avg();

                $articleViewed = SubscriberEvent::where('name', '=', 'article.viewed')
                    ->whereBetween('occurred_at', $range)
                    ->count();

                $articleUniqueViewed = SubscriberEvent::where('name', '=', 'article.viewed')
                    ->whereBetween('occurred_at', $range)
                    ->get()
                    ->groupBy('subscriber_id')
                    ->map(function (Collection $collection, int $key) {
                        if ($key === 0) {
                            return $collection
                                ->groupBy('anonymous_id')
                                ->map(function (Collection $collection) {
                                    return $collection->unique('target_id')->count();
                                });
                        }

                        return $collection->unique('target_id')->count();
                    })
                    ->flatten()
                    ->sum();

                $emailCollected = Subscriber::where('id', '>', 0)
                    ->where('signed_up_source', '!=', 'import')
                    ->whereBetween('created_at', $range)
                    ->count();

                $emailSent = SubscriberEvent::where('name', '=', 'prophet.email.sent')
                    ->whereBetween('occurred_at', $range)
                    ->count();

                $emailReplied = SubscriberEvent::where('name', '=', 'prophet.email.replied')
                    ->whereBetween('occurred_at', $range)
                    ->count();

                $conditions = $monthly ? [
                    'year' => $date->year,
                    'month' => $date->month,
                ] : [
                    'date' => $date->toDateString(),
                ];

                ArticleAnalysis::updateOrCreate($conditions, [
                    'data' => [
                        'article_avg_scrolled' => $articleAvgScrolled ?: 0,
                        'article_read' => $articleRead->count(),
                        'article_unique_read' => $articleUniqueRead,
                        'article_viewed' => $articleViewed,
                        'article_unique_viewed' => $articleUniqueViewed,
                        'email_collected' => $emailCollected,
                        'email_collected_ratio' => $emailCollected / max($articleViewed, 1),
                        'email_sent' => $emailSent,
                        'email_replied' => $emailReplied,
                        'email_replied_ratio' => $emailReplied / max($emailSent, 1),
                    ],
                ]);

                if ($monthly) {
                    return;
                }

                $articles = SubscriberEvent::query()
                    ->whereIn('name', [
                        'article.viewed',
                        'article.read',
                    ])
                    ->get()
                    ->groupBy('target_id');

                foreach ($articles as $id => $events) {
                    $read = $events->where('name', '=', 'article.read')->count();

                    $uniqueRead = $events
                        ->where('name', '=', 'article.read')
                        ->groupBy('subscriber_id')
                        ->map(function (Collection $collection, int $key) {
                            if ($key === 0) {
                                return $collection
                                    ->groupBy('anonymous_id')
                                    ->count();
                            }

                            return 1;
                        })
                        ->flatten()
                        ->sum();

                    $avgScrolled = $events
                        ->where('name', '=', 'article.read')
                        ->groupBy('subscriber_id')
                        ->map(function (Collection $collection, int $key) {
                            if ($key === 0) {
                                return $collection
                                    ->groupBy('anonymous_id')
                                    ->map(function (Collection $collection) {
                                        return $collection->max('data.percentage');
                                    });
                            }

                            return $collection->max('data.percentage');
                        })
                        ->flatten()
                        ->avg();

                    $viewed = $events->where('name', '=', 'article.viewed')->count();

                    $uniqueViewed = $events
                        ->where('name', '=', 'article.viewed')
                        ->groupBy('subscriber_id')
                        ->map(function (Collection $collection, int $key) {
                            if ($key === 0) {
                                return $collection
                                    ->groupBy('anonymous_id')
                                    ->count();
                            }

                            return 1;
                        })
                        ->flatten()
                        ->sum();

                    $signedUp = SubscriberEvent::where('name', '=', 'subscriber.signed_in')
                        ->whereJsonContains('data->article_id', (string) $id)
                        ->distinct('subscriber_id')
                        ->count();

                    $analysis = ArticleAnalysis::orderBy('id')->updateOrCreate([
                        'article_id' => $id,
                    ], [
                        'data' => [
                            'avg_scrolled' => $avgScrolled ?: 0,
                            'read' => $read,
                            'unique_read' => $uniqueRead,
                            'viewed' => $viewed,
                            'unique_viewed' => $uniqueViewed,
                            'email_collected' => $signedUp,
                            'email_collected_ratio' => $signedUp / max($viewed, 1),
                        ],
                    ]);

                    ArticleAnalysis::where('article_id', '=', $id)
                        ->where('id', '!=', $analysis->id)
                        ->delete();
                }
            },
            $tenants->lazyById(50),
        );

        if (! $monthly) {
            $this->call(static::class, [
                '--date' => $date->toDateString(),
                '--tenants' => $this->option('tenants'),
                '--monthly' => true,
            ]);
        }

        return static::SUCCESS;
    }
}
