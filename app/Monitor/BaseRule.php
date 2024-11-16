<?php

namespace App\Monitor;

use App\Models\Rule;

abstract class BaseRule
{
    /**
     * message format
     */
    protected string $message;

    /**
     * array of the output message
     *
     * @var string[]
     */
    protected array $messages = [];

    protected Rule $rule;

    /**
     * a flag that check from last monitor time to now
     */
    public bool $within = true;

    /**
     * a flag means the threshold is a percentage of the total count
     */
    public bool $percentage = false;

    /**
     * a filter list of tenant keys
     *
     * @var string[]
     */
    protected array $blacklist = [
        // e2e tenants
        'DUH7PTZVD',
        'D3YSN1OAL',
    ];

    /**
     * Check the rule is passed or not.
     */
    abstract public function check(): bool;

    /**
     * Check the rule settings is valid.
     */
    public function validate(): bool
    {
        if ($this->rule->timer < 1) {
            return false;
        }

        if ($this->percentage) {
            return $this->rule->threshold > 0 && $this->rule->threshold <= 100;
        }

        return true;
    }

    /**
     * whether the rule should be executed based on the frequency config
     */
    public function shouldRun(): bool
    {
        $lastRanAt = $this->rule->last_ran_at;

        if ($lastRanAt === null) {
            return true;
        }

        $lastExecutedAt = ($this->within)
            ? $lastRanAt
            : $lastRanAt->copy()->addSeconds($this->rule->timer);

        return $lastExecutedAt->addSeconds($this->rule->frequency)->isPast();
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function setRule(Rule $rule): void
    {
        $this->rule = $rule;
    }
}
