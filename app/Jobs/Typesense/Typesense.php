<?php

namespace App\Jobs\Typesense;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type TModel \App\Models\Tenants\Article|\App\Models\Tenants\Subscriber
 */
abstract class Typesense implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    protected string $tenant;

    /**
     * @var class-string<TModel>
     */
    protected string $model;

    /**
     * @var array<int, int>
     */
    protected array $ids = [];

    /**
     * Create a new job instance.
     *
     * @param  Collection<int, TModel>  $models
     *
     * @throws TenancyNotInitializedException
     */
    public function __construct(Collection $models)
    {
        if ($models->isEmpty()) {
            return;
        } elseif (!tenancy()->initialized) {
            throw new TenancyNotInitializedException();
        }

        $this->tenant = tenant_or_fail()->id;

        /** @var TModel $model */
        $model = $models->first();

        $this->model = get_class($model);

        $ids = $models->pluck('id')->toArray();

        Assert::allPositiveInteger($ids);

        $this->ids = $ids;
    }
}
