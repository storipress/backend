<?php

namespace App\Console\Commands\Monitor;

use App\Models\Action;
use Illuminate\Console\Command;

class DeleteRuleAction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:action:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'delete a monitor rule action';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actions = Action::get('id');

        if ($actions->isEmpty()) {
            $this->error('No actions found');

            return self::FAILURE;
        }

        $this->call('monitor:rule:action:list');

        $ids = $actions->pluck('id')->all();

        $id = $this->choice(
            question: 'Which action do you want to delete?',
            choices: $ids,
        );

        /** @var Action $action */
        $action = $actions->where('id', $id)->first();

        $action->rules()->detach();

        $action->delete();

        $this->call('monitor:rule:action:list');

        return self::SUCCESS;
    }
}
