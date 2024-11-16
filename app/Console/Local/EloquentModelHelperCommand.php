<?php

namespace App\Console\Local;

use App\Models\Tenant;
use App\Models\Tenants\Entity;
use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Doctrine\DBAL\Exception;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Webmozart\Assert\Assert;

class EloquentModelHelperCommand extends ModelsCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ide-helper:model';

    protected Tenant $tenant;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var Tenant|null $tenant */
        $tenant = tenancy()->query()->first();

        if (is_null($tenant)) {
            $this->error(
                'Please ensures that there is at least one tenant.',
            );

            return 1;
        }

        $config = $tenant->run(
            fn () => config('database.connections.tenant'),
        );

        Assert::isArray($config);

        $this->config = $config;

        $this->tenant = $tenant;

        parent::handle();

        return 0;
    }

    /**
     * Load the properties from the database table.
     *
     * @param  Model  $model
     *
     * @throws Exception
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function getPropertiesFromTable($model): void
    {
        $instances = [
            Entity::class,
        ];

        foreach ($instances as $instance) {
            if ($model instanceof $instance) {
                tenancy()->initialize($this->tenant);

                break;
            }
        }

        parent::getPropertiesFromTable($model);

        tenancy()->end();

        config(['database.connections.tenant' => $this->config]);
    }
}
