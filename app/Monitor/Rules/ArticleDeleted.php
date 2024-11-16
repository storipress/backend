<?php

namespace App\Monitor\Rules;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;

class ArticleDeleted extends BaseRule
{
    public string $message = 'Publication %s has deleted %s%% (>= %s%%) articles within %s';

    public string $description = 'Detect the number of the user deleted article is large than threshold.';

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

                $exists = Article::count();

                $deletes = Article::onlyTrashed()
                    ->whereBetween('deleted_at', [$from, $to])
                    ->count();

                if ($exists === 0 && $deletes === 0) {
                    return;
                }

                $value = $deletes / ($exists + $deletes) * 100;

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
