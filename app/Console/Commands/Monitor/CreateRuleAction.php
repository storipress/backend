<?php

namespace App\Console\Commands\Monitor;

use App\Enums\Monitor\Action as ActionEnum;
use App\Models\Action;
use Illuminate\Console\Command;
use Psr\Log\LogLevel;
use ReflectionClass;

class CreateRuleAction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:rule:action:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create a new monitor rule action';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $actionsType = ActionEnum::getKeys();

        /** @var string $type */
        $type = $this->choice(
            question: 'What is the action type?',
            choices: $actionsType,
        );

        $data = $this->askOptions($type);

        $name = $this->ask('What is the custom name of the action?');

        Action::create([
            'name' => $name,
            'data' => $data,
            'type' => $type,
        ]);

        $this->call('monitor:rule:action:list');

        return self::SUCCESS;
    }

    /**
     * ask by type and return the data.
     *
     * @return string[]
     */
    private function askOptions(string $type): array
    {
        $url = $level = '';

        switch ($type) {
            case 'slack':
                /** @var string $url */
                while (($url = $this->ask('What is the webhook url?')) === null) {
                    $this->error('Webhook url is required');
                }

                break;
            case 'log':
                /** @var string $level */
                $level = $this->choice(
                    question: 'What is the log level?',
                    choices: array_values((new ReflectionClass(LogLevel::class))->getConstants()),
                );

                break;
        }

        /** @var string[] $data */
        $data = match ($type) {
            'slack' => [
                'webhook_url' => $url,
            ],
            'log' => [
                'level' => $level,
            ],
            default => [],
        };

        return empty($data) ? [] : $data;
    }
}
