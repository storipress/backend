<?php

namespace App\Monitor\Rules;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\UserActivity;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;

class ArticleContentUpdated extends BaseRule
{
    public string $message = 'Publication %s has updated %s%% (>= %s%%) articles\' content within %s';

    public string $description = 'Detect the number of articles that content was updated exceeded the threshold.';

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
                if (!$tenant->initialized) {
                    return;
                }

                $count = Article::count();

                if ($count === 0) {
                    return;
                }

                $activityCount = UserActivity::whereBetween('occurred_at', [$from, $to])
                    ->where('name', 'article.content.update')
                    ->distinct()
                    ->count('subject_id');

                $value = $activityCount / $count * 100;

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
