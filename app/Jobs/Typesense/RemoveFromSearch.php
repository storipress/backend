<?php

namespace App\Jobs\Typesense;

use App\Models\Tenant;
use Http\Client\Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Engines\Engine;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Throwable;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

/**
 * @phpstan-import-type TModel from Typesense
 */
class RemoveFromSearch extends Typesense
{
    /**
     * Handle the job.
     *
     *
     * @throws Exception
     * @throws TenantCouldNotBeIdentifiedById
     * @throws TypesenseClientError
     */
    public function handle(): void
    {
        if (empty($this->ids)) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()->find($this->tenant);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            try {
                /** @var TModel $instance */
                $instance = new $this->model();

                $engine = $instance->searchableUsing();

                Assert::isInstanceOf($engine, Engine::class);

                foreach ($this->ids as $id) {
                    $instance->setAttribute('id', $id);

                    try {
                        $engine->delete(
                            new Collection([$instance]),
                        );
                    } catch (ObjectNotFound) {
                        // ignored
                    } catch (Throwable $e) {
                        captureException($e);
                    }
                }
            } catch (Throwable $e) {
                captureException($e);
            }
        });
    }
}
