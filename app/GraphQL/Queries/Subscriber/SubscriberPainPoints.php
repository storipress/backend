<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\Subscriber;

use App\Enums\Analyze\Type;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\AiAnalysis;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use Illuminate\Database\Eloquent\Relations\HasMany;

final readonly class SubscriberPainPoints
{
    /**
     * @param  array{
     *     id: string,
     * }  $args
     * @return array<int, array{
     *     weight: int,
     *     value: string,
     * }>
     */
    public function __invoke(null $_, array $args): array
    {
        $subscriber = Subscriber::withoutEagerLoads()
            ->with([
                'events' => function (HasMany $query) {
                    $query->whereIn('name', ['article.seen', 'article.link.clicked'])
                        ->where('occurred_at', '>=', now()->startOfDay()->subMonths(3))
                        ->orderByDesc('occurred_at')
                        ->select('subscriber_id', 'target_id');
                },
            ])
            ->find($args['id']);

        if (! ($subscriber instanceof Subscriber)) {
            throw new ErrorException(ErrorCode::NOT_FOUND);
        }

        $articleIds = $subscriber->events
            ->pluck('target_id')
            ->unique()
            ->values()
            ->toArray();

        // @phpstan-ignore-next-line
        return AiAnalysis::withoutEagerLoads()
            ->where('target_type', '=', Article::class)
            ->whereIn('target_id', $articleIds)
            ->where('type', '=', Type::articlePainPoints())
            ->get()
            ->flatMap(function (AiAnalysis $analysis) {
                $insights = array_slice(
                    $analysis->data['insights'] ?? [],
                    0,
                    3,
                );

                return collect($insights)
                    ->sortByDesc('weight')
                    ->map(function (array $insight) {
                        return [
                            'weight' => (int) $insight['weight'],
                            'value' => $insight['pain_point'] ?? $insight['pain point'],
                        ];
                    })
                    ->toArray();
            })
            ->take(10)
            ->values()
            ->toArray();
    }
}
