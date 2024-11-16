<?php

namespace App\Jobs\Entity\Subscriber;

use App\Enums\Analyze\Type;
use App\Jobs\Revert\SyncPainPointToHubSpot;
use App\Models\Tenant;
use App\Models\Tenants\AiAnalysis;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use App\Queue\Middleware\WithoutOverlapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class AnalyzeSubscriberPainPoints implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * @var array<string, positive-int>
     */
    public array $weights = [
        'article.seen' => 1,
        'article.link.clicked' => 2,
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public int $subscriberId,
    ) {
        //
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlappingKey()))
                ->dontRelease(),
        ];
    }

    /**
     * The job's unique key used for preventing overlaps.
     */
    public function overlappingKey(): string
    {
        return sprintf('%s:%s', $this->tenantId, $this->subscriberId);
    }

    /**
     * Event Weight Calculation:
     * - Article view: 1 point (max 3 points).
     * - Link click: 2 points (max 6 points).
     * - The single event limit is the score times 3.
     *
     * Normalization Process:
     * 1. Multiply each article insight's pain point weight by its event weight.
     * 2. Normalize to fit within 1-100 by finding the maximum event weight, multiplying it by 100 for the potential max value, then dividing each result by this max value and multiplying by 100.
     *
     * Example Calculation:
     * - Article 1 event weight: 4 points
     * - Article 2 event weight: 2 points
     * - Article 1 insights: weights of 80 and 70
     * - Article 2 insights: weights of 85 and 75
     * - Post-calculation, insights from Article 1 are 320 and 280; Article 2 are 170 and 150.
     * - Normalize by dividing each by the maximum possible value (400 in this case) and multiply by 100.
     *
     * Results:
     * - Insight 1 from Article 1: 80
     * - Insight 2 from Article 1: 70
     * - Insight 1 from Article 2: 42.5
     * - Insight 2 from Article 2: 37.5
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        if (!$tenant->has_prophet) {
            return;
        }

        $tenant->run(function () {
            $subscriber = Subscriber::with([
                'events' => function (HasMany $query) {
                    $query->whereIn('name', ['article.seen', 'article.link.clicked'])
                        ->where('occurred_at', '>=', now()->startOfDay()->subMonths(3))
                        ->select('name', 'subscriber_id', 'target_id');
                },
                'pain_point',
            ])
                ->find($this->subscriberId);

            if (!($subscriber instanceof Subscriber)) {
                return;
            }

            // calculate each article's weights
            $weights = $subscriber
                ->events
                ->groupBy(['target_id', 'name'])
                ->map(function (Collection $items) {
                    // @phpstan-ignore-next-line
                    $events = $items->map(function (Collection $event, string $key) {
                        return [
                            'name' => $key,
                            'count' => $event->count(),
                        ];
                    });

                    return array_reduce(
                        $events->toArray(),
                        function ($carry, $event) {
                            // set an upper limit to prevent the event weight from being overly influenced.
                            return $carry + ($this->weights[$event['name']] ?? 0) * min(5, $event['count']); // @phpstan-ignore-line
                        },
                        0,
                    );
                })
                ->sortDesc();

            if ($weights->isEmpty()) {
                return;
            }

            $insights = AiAnalysis::withoutEagerLoads()
                ->where('target_type', '=', Article::class)
                ->whereIn('target_id', $weights->keys())
                ->where('type', '=', Type::articlePainPoints())
                ->get()
                ->flatMap(function (AiAnalysis $analysis) use ($weights) {
                    $weight = $weights->get($analysis->target_id);

                    /**
                     * @var array<int, array{
                     *     "goal": string,
                     *     "tags": string,
                     * }> $data
                     */
                    $data = $analysis->data['first_order'] ?? ($analysis->data['first order'] ?? []);

                    return collect($data)
                        ->map(function (array $insight) use ($weight) {
                            return [
                                'goal' => $insight['goal'],
                                'weight' => $weight,
                            ];
                        })
                        ->toArray();
                })
                ->sortByDesc('weight');

            if ($insights->isEmpty()) {
                return;
            }

            $payload = [
                'data' => $insights->toJson(),
            ];

            $analysis = $subscriber->pain_point;

            $checksum = hmac($payload, true, 'md5');

            if ($analysis && hash_equals($analysis->checksum, $checksum)) {
                return;
            }

            $subscriber->pain_point()->updateOrCreate([
                'type' => Type::subscriberPainPoints(),
            ], [
                'checksum' => $checksum,
                'data' => $insights->toArray(),
            ]);

            SyncPainPointToHubSpot::dispatchSync(
                $this->tenantId,
                $this->subscriberId,
            );
        });
    }
}
