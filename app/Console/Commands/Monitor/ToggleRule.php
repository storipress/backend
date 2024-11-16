<?php

namespace App\Console\Commands\Monitor;

use App\Models\Rule;
use Illuminate\Console\Command;

class ToggleRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:toggle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'toggle a monitor rule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('monitor:rule:list');

        $rules = Rule::get(['id', 'activated_at']);

        $ids = $rules->pluck('id')->all();

        $toggleId = $this->choice(
            question: 'Which rule do you want to toggle?',
            choices: $ids,
        );

        /** @var Rule $rule */
        $rule = $rules->where('id', $toggleId)->first();

        $rule->update([
            'activated_at' => $rule->activated_at ? null : now(),
        ]);

        $this->call('monitor:rule:list');

        return self::SUCCESS;
    }
}
