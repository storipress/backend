<?php

namespace App\Monitor;

use App\Enums\Monitor\Action as ActionEnum;
use App\Enums\Monitor\Rule as RuleEnum;
use App\Models\Rule;
use Illuminate\Support\Facades\Log;

class Monitor
{
    /**
     * @var BaseRule[]
     */
    protected array $rules = [];

    /**
     * @var BaseAction[]
     */
    protected array $actions = [];

    public function run(): void
    {
        $rules = Rule::whereNotNull('activated_at')
            ->orderBy('type')
            ->orderBy('exclusive', 'desc')
            ->get();

        $exclusive = [];

        foreach ($rules as $rule) {
            $checker = $this->getChecker($rule->type);

            if ($checker === null) {
                Log::debug('Rule not found', [
                    'rule' => $rule,
                ]);

                continue;
            }

            $lastRanAt = $checker->within ? now() : now()->subSeconds($rule->timer);

            $checker->setRule($rule);

            if (! $checker->validate()) {
                Log::debug('Rule is invalid', [
                    'rule' => $rule,
                ]);

                continue;
            }

            if (! $checker->shouldRun()) {
                continue;
            }

            if ($checker->check()) {
                $rule->update(['last_ran_at' => $lastRanAt]);

                continue;
            }

            if (isset($exclusive[$rule->type])) {
                continue;
            }

            if ($rule->exclusive) {
                $exclusive[$rule->type] = true;
            }

            $actions = $rule->actions()->get();

            $messages = $checker->getMessages();

            foreach ($actions as $action) {
                $data = [
                    'data' => $action->data,
                    'messages' => $messages,
                ];

                $this->getAction($action->type)?->run($data);
            }

            $rule->update(['last_ran_at' => $lastRanAt]);
        }
    }

    public function getChecker(string $type): ?BaseRule
    {
        if (! RuleEnum::hasKey($type)) {
            return null;
        }

        if (! isset($this->rules[$type])) {
            $class = RuleEnum::getValue($type);

            $object = new $class();

            $this->rules[$type] = $object;
        }

        return $this->rules[$type];
    }

    protected function getAction(string $type): ?BaseAction
    {
        if (! ActionEnum::hasKey($type)) {
            return null;
        }

        if (! isset($this->actions[$type])) {
            $class = ActionEnum::getValue($type);

            $object = new $class();

            $this->actions[$type] = $object;
        }

        return $this->actions[$type];
    }
}
