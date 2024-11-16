<?php

namespace App\Console\Commands\Monitor;

use App\Models\Action;
use Illuminate\Console\Command;

class ListRuleAction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:action:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'list all monitor rule actions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('Actions:');

        $actions = Action::all();

        $this->table(['ID', 'Name', 'Type', 'Data'], $actions->map(function (Action $action) {
            return [
                $action->id,
                $action->name,
                $action->type,
                json_encode($action->data),
            ];
        }));

        return self::SUCCESS;
    }
}
