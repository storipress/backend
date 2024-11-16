<?php

namespace App\Console\Commands\Monitor;

use App\Models\Rule;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;

class ListRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'list all monitor rules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('Rules:');

        $rules = Rule::all();

        $this->table(
            ['ID', 'Type', 'Timer', 'Threshold', 'Actions', 'Frequency', 'Multi Check', 'Exclusive', 'Activated At'],
            $rules->map(function ($rule) {
                return [
                    $rule->id,
                    $rule->type,
                    CarbonInterval::seconds($rule->timer)->cascade()->forHumans(),
                    $rule->threshold,
                    $rule->actions->map->name,
                    CarbonInterval::seconds($rule->frequency)->cascade()->forHumans(),
                    $rule->multi_check,
                    $rule->exclusive,
                    $rule->activated_at,
                ];
            }));

        return self::SUCCESS;
    }
}
