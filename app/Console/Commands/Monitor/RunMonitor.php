<?php

namespace App\Console\Commands\Monitor;

use App\Monitor\Monitor;
use Illuminate\Console\Command;

class RunMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run the monitor';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monitor = new Monitor();

        $monitor->run();

        return self::SUCCESS;
    }
}
