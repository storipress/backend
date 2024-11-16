<?php

namespace App\Monitor\Rules;

use App\Models\Email;
use App\Models\Tenant;
use App\Models\User;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;
use Illuminate\Support\Arr;

class MassInvitation extends BaseRule
{
    public string $message = 'Publication %s has %s (>= %s) successful invitations within %s';

    public string $description = 'Detect the number of successful invitations is large than threshold.';

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

        $threshold = $this->rule->threshold;

        $emailsData = Email::whereBetween('created_at', [$from, $to])
            ->where('template_id', 27796821)
            ->get(['tenant_id', 'to', 'data'])
            ->toArray();

        // filter the emails which are registered
        $data = Arr::where($emailsData, function ($value) {
            return isset($value['data']['inviter']) && $value['tenant_id'] !== 'N/A';
        });

        $emailsList = [];

        foreach ($data as $value) {
            if (! isset($emailsList[$value['tenant_id']])) {
                $emailsList[$value['tenant_id']] = [];
            }

            $emailsList[$value['tenant_id']][] = $value['to'];
        }

        $publications = [];

        foreach ($emailsList as $name => $emails) {
            $tenant = Tenant::find($name);

            if ($tenant === null) {
                continue;
            }

            $ids = User::whereIn('email', $emails)->pluck('id')->all();

            $count = $tenant->users()->whereIn('user_id', $ids)->count();

            if ($count < $threshold) {
                continue;
            }

            $publications[$name] = $count;
        }

        foreach ($publications as $tenant => $value) {
            $this->messages[] = sprintf($this->message,
                $tenant,
                $value,
                $threshold,
                CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            );
        }

        return count($publications) === 0;
    }
}
