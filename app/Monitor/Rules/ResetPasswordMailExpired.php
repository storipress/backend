<?php

namespace App\Monitor\Rules;

use App\Models\Email;
use App\Models\PasswordReset;
use App\Monitor\BaseRule;
use Carbon\CarbonInterval;
use Illuminate\Support\Arr;

class ResetPasswordMailExpired extends BaseRule
{
    public string $message = 'Found %s reset password email(s) were not used after %s: %s';

    public string $description = 'Detect reset password mail was not used after a period time.';

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

        $emailsData = Email::whereBetween('created_at', [$from, $to])
            ->where('template_id', 27648921)
            ->get(['to', 'data'])
            ->toArray();

        $data = [];

        /** @var array{to:string, data:array{action_url:string}} $value */
        foreach ($emailsData as $value) {
            $url = $value['data']['action_url'];

            /** @var string $query */
            $query = parse_url($url, PHP_URL_QUERY);

            parse_str($query, $result);

            $data[$result['token']] = $value['to']; // @phpstan-ignore-line
        }

        $tokens = array_keys($data);

        // if user reset password, this token will be deleted
        $unusedTokens = PasswordReset::whereIn('token', $tokens)
            ->pluck('token')
            ->all();

        $count = count($unusedTokens);

        $emails = [];

        foreach ($unusedTokens as $token) {
            $emails[] = $data[$token];
        }

        $emails = array_unique($emails);

        $this->message = sprintf($this->message,
            $count,
            CarbonInterval::seconds($this->rule->timer)->cascade()->forHumans(),
            Arr::join($emails, ', '),
        );

        return $count === 0;
    }
}
