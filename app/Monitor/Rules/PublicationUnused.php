<?php

namespace App\Monitor\Rules;

use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;
use Segment\Segment;

class PublicationUnused extends BaseRule
{
    public string $message = 'Publication %s is not used after %s';

    public string $description = 'Detect publication is not used.';

    /**
     * {@inheritdoc}
     */
    public bool $within = false;

    /**
     * {@inheritdoc}
     */
    public bool $percentage = false;

    public function check(): bool
    {
        $to = now()->subSeconds($this->rule->timer);

        $from = $to->copy()->subSeconds($this->rule->timer * $this->rule->multi_check);

        $tenants = Tenant::whereBetween('created_at', [$from, $to])
            ->whereNotIn('id', $this->blacklist)
            ->get();

        $publications = [];

        tenancy()->runForMultiple(
            $tenants,
            function (Tenant $tenant) use (&$publications) {
                if (! $tenant->initialized) {
                    return;
                }

                $count = UserActivity::count();

                if ($count > 0) {
                    return;
                }

                $publications[$tenant->getKey()] = $count;

                Segment::track([
                    'userId' => (string) $tenant->owner->id,
                    'event' => 'tenant_unused',
                    'properties' => [
                        'tenant_uid' => $tenant->id,
                        'tenant_name' => $tenant->name,
                    ],
                    'context' => [
                        'groupId' => $tenant->id,
                    ],
                ]);
            },
        );

        foreach ($publications as $name => $value) {
            $this->messages[] = sprintf($this->message,
                $name,
                CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            );
        }

        return count($publications) === 0;
    }
}
