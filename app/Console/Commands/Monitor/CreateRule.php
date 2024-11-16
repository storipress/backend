<?php

namespace App\Console\Commands\Monitor;

use App\Enums\Monitor\Rule as RuleEnum;
use App\Models\Action;
use App\Models\Rule;
use App\Monitor\BaseRule;
use Illuminate\Console\Command;

class CreateRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create a new monitor rule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actionIds = Action::pluck('id')->all();

        if (empty($actionIds)) {
            $this->error('No actions found (Please run `php artisan monitor:rule:action:create`)');

            return self::FAILURE;
        }

        $rulesType = RuleEnum::getKeys();

        /** @var string $type */
        $type = $this->choice(
            question: 'What is the rule type?',
            choices: $rulesType,
        );

        while (true) {
            $timer = $this->ask('What is the timer (seconds) ?');

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
        $class = RuleEnum::getValue($type);

        $base = new $class();

        while (true) {
            $question = ($base->percentage)
                    ? 'What is the threshold ( 1 - 100 )%?'
                    : 'What is the threshold ( > 0 )?';

            $threshold = $this->ask($question);

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
            ? 1
            : $this->ask('How many times of timer do you want to check?', '1');

        while (true) {
            $frequency = $this->ask('What is the frequency (seconds) ?');

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

        $this->call('monitor:rule:action:list');

        /** @var string[] $selectIds */
        $selectIds = $this->choice(
            question: 'Choose at least one action if the rule is not passed.',
            choices: $actionIds,
            multiple: true,
        );

        $exclusive = $this->confirm('Do you wish to set exclusive?');

        $enable = $this->confirm('Do you wish to enable right now?');

        $rule = Rule::create([
            'type' => $type,
            'timer' => $timer,
            'threshold' => $threshold,
            'activated_at' => ($enable) ? now() : null,
            'exclusive' => $exclusive,
            'multi_check' => $multiCheck,
            'frequency' => $frequency,
        ]);

        $rule->actions()->attach($selectIds);

        $this->call('monitor:rule:list');

        return self::SUCCESS;
    }
}
