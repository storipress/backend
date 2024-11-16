<?php

namespace App\Monitor\Rules;

use App\Models\Tenant;
use App\Models\Tenants\ReleaseEvent;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;

class MaxBuildAttemptsExceeded extends BaseRule
{
    public string $message = 'Publication %s has %s release build errors (attempts >= %s) within %s';

    public string $description = 'Detect the number of build errors that retry times are large than threshold.';

    /**
     * {@inheritdoc}
     */
    public bool $within = true;

    /**
     * {@inheritdoc}
     */
    public bool $percentage = false;

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

                $count = ReleaseEvent::whereBetween('created_at', [$from, $to])
                    ->where('attempts', '>=', $threshold)
                    ->count();

                if ($count === 0) {
                    return;
                }

                $publications[$tenant->getKey()] = $count;
            },
        );

        foreach ($publications as $name => $value) {
            $this->messages[] = sprintf($this->message,
                $name,
                $value,
                $threshold,
                CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            );
        }

        return count($publications) === 0;
    }
}
