<?php

namespace App\Console\Commands\Monitor;

use App\Enums\Monitor\Rule as RuleEnum;
use App\Models\Action;
use App\Models\Rule;
use App\Monitor\BaseRule;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class UpdateRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update a monitor rule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('monitor:rule:list');

        $rules = Rule::get();

        $ids = $rules->pluck('id')->all();

        $updateId = $this->choice(
            question: 'Which rule do you want to update?',
            choices: $ids,
        );

        /** @var Rule $rule */
        $rule = $rules->where('id', $updateId)->first();

        while (true) {
            $timer = $this->ask('What is the timer (seconds) ?', strval($rule->timer));

            if (!ctype_digit($timer)) {
                $this->error('Timer must be an integer');

                continue;
            }

            $timer = intval($timer);

            if ($timer < 1) {
                $this->error('Timer must be greater than 0');
            } else {
                break;
            }
        }

        /** @var BaseRule $class */
        $class = RuleEnum::getValue($rule->type);

        $base = new $class();

        while (true) {
            $question = ($base->percentage)
                ? 'What is the threshold ( 1 - 100 )%?'
                : 'What is the threshold ( > 0 )?';

            $threshold = $this->ask($question, strval($rule->threshold));

            if (!ctype_digit($threshold)) {
                $this->error('Threshold must be an integer');

                continue;
            }

            $threshold = intval($threshold);

            if ($threshold < 1) {
                $this->error('Threshold must be greater than 0');
            } elseif ($base->percentage && $threshold > 100) {
                $this->error('Threshold must be between 1 and 100');
            } else {
                break;
            }
        }

        $multiCheck = ($base->within)
            ? $rule->multi_check
            : $this->ask('How many times of timer do you want to check?', strval($rule->multi_check));

        if (!is_int($multiCheck) && !ctype_digit($multiCheck)) {
            $multiCheck = $rule->multi_check;
        }

        while (true) {
            $frequency = $this->ask('What is the frequency (seconds) ?', strval($rule->frequency));

            if (!ctype_digit($frequency)) {
                $this->error('Frequency must be an integer');

                continue;
            }

            $frequency = intval($frequency);

            if ($frequency < 1) {
                $this->error('Frequency must be greater than 0');
            } else {
                break;
            }
        }

        $exclusive = $this->confirm('Do you wish to set exclusive?', $rule->exclusive);

        $actionsIds = Action::pluck('id')->all();

        $currentActionIds = $rule->actions->pluck('id')->all();

        $default = [];

        foreach ($actionsIds as $index => $id) {
            if (!in_array($id, $currentActionIds)) {
                continue;
            }

            $default[] = $index;
        }

        $this->call('monitor:rule:action:list');

        /** @var string[] $selectIds */
        $selectIds = $this->choice(
            question: 'Which actions do you want to attach or detach?',
            choices: $actionsIds,
            default: empty($default) ? null : Arr::join($default, ','),
            multiple: true,
        );

        $rule->update([
            'timer' => $timer,
            'threshold' => $threshold,
            'multi_check' => intval($multiCheck),
            'frequency' => $frequency,
            'exclusive' => $exclusive,
        ]);

        $rule->actions()->toggle($selectIds);

        $this->call('monitor:rule:list');

        return self::SUCCESS;
    }
}
