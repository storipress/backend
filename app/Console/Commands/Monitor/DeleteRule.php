<?php

namespace App\Console\Commands\Monitor;

use App\Models\Rule;
use Illuminate\Console\Command;

class DeleteRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'delete a monitor rule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rules = Rule::get('id');

        if ($rules->isEmpty()) {
            $this->error('No rules found');

            return self::FAILURE;
        }

        $this->call('monitor:rule:list');

        $ids = $rules->pluck('id')->all();

        $deleteId = $this->choice(
            question: 'Which rule do you want to delete?',
            choices: $ids,
        );

        /** @var Rule $rule */
        $rule = $rules->where('id', $deleteId)->first();

        $rule->actions()->detach();

        $rule->delete();

        $this->call('monitor:rule:list');

        return self::SUCCESS;
    }
}
