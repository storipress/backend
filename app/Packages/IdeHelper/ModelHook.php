<?php

namespace App\Packages\IdeHelper;

use App\Models\AccessToken;
use App\Models\Tenants\Release;
use App\Models\User;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Illuminate\Database\Eloquent\Model;

class ModelHook implements ModelHookInterface
{
    /**
     * @var array<class-string, array<int, array<int, string>>>
     *
     * The fields are $name, $type, $read, $write, $comment, $nullable
     */
    protected array $mapping = [
        AccessToken::class => [
            ['tokenable_type', 'string'],
            ['tokenable_id', 'string'],
        ],
        User::class => [
            ['password', 'string'],
        ],
        Release::class => [
            ['state', '\App\Enums\Release\State'],
        ],
    ];

    public function run(ModelsCommand $command, Model $model): void
    {
        foreach ($this->mapping as $class => $fields) {
            if ($model instanceof $class) {
                foreach ($fields as $field) {
                    $command->setProperty(...$field);
                }
            }
        }
    }
}
