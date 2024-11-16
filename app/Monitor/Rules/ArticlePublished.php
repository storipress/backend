<?php

namespace App\Monitor\Rules;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ReleaseEvent;
use App\Models\Tenants\UserActivity;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;

class ArticlePublished extends BaseRule
{
    public string $message = 'Publication %s has published and unpublished %s%% (>= %s%%) articles within %s';

    public string $description = 'Detect the number of publish and unpublished article exceeded the threshold.';

    /**
     * {@inheritdoc}
     */
    public bool $within = true;

    /**
     * {@inheritdoc}
     */
    public bool $percentage = true;

    public function check(): bool
    {
        $to = now();

        $from = $to->copy()->subSeconds($this->rule->timer);

        $publications = [];

        $threshold = $this->rule->threshold;

        $tenants = Tenant::whereNotIn('id', $this->blacklist)->get();

        tenancy()->runForMultiple(
            $tenants,
            function (Tenant $tenant) use ($to, $from, $threshold, &$publications) {
                if (! $tenant->initialized) {
                    return;
                }

                $count = Article::count();

                if ($count === 0) {
                    return;
                }

                $publishIds = ReleaseEvent::whereBetween('created_at', [$from, $to])
                    ->whereIn('name', ['article:publish', 'article:schedule'])
                    ->whereNotNull('data')
                    ->pluck('data')
                    ->flatten()
                    ->unique()
                    ->all();

                $unpublishIds = UserActivity::whereBetween('occurred_at', [$from, $to])
                    ->where('name', 'article.unschedule')
                    ->distinct()
                    ->pluck('subject_id')
                    ->all();

                $ids = array_unique(array_merge($publishIds, $unpublishIds));

                $value = count($ids) / $count * 100;

                if ($value < $threshold) {
                    return;
                }

                $publications[$tenant->getKey()] = $value;
            },
        );

        foreach ($publications as $name => $value) {
            $this->messages[] = sprintf($this->message,
                $name,
                ceil($value),
                $threshold,
                CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            );
        }

        return count($publications) === 0;
    }
}
