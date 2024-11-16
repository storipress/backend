<?php

namespace App\Monitor\Rules;

use App\Models\Email;
use App\Models\User;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;
use Illuminate\Support\Arr;

class ConfirmMailExpired extends BaseRule
{
    public string $message = 'Found %s email(s) were not confirmed after %s: %s';

    public string $description = 'Detect mail is not confirmed too long';

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

        $emails = Email::whereBetween('created_at', [$from, $to])
            ->where('template_id', 27682762)
            ->pluck('to')
            ->all();

        $unverifiedEmails = User::whereIn('email', $emails)
            ->whereNull('verified_at')
            ->pluck('email')
            ->all();

        $count = count($unverifiedEmails);

        $this->message = sprintf($this->message,
            $count,
            CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            Arr::join($unverifiedEmails, ', '),
        );

        return $count === 0;
    }
}
