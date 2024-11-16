<?php

namespace App\Jobs\Typesense;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use Exception;
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
class MakeSearchable extends Typesense
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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            try {
                $instance = new $this->model();

                $engine = $instance->searchableUsing();

                Assert::isInstanceOf($engine, Engine::class);

                if ($instance instanceof Article) {
                    $instance = $instance->withoutEagerLoads()
                        ->has('desk')
                        ->with([
                            'stage',
                            'layout',
                            'desk',
                            'desk.layout',
                            'desk.desk',
                            'desk.desk.layout',
                            'authors',
                            'authors.parent',
                            'authors.parent.avatar',
                            'tags',
                        ]);
                }

                $data = $instance->whereIn('id', $this->ids)->get();

                if ($data->isEmpty()) {
                    return;
                }

                $engine->update($data);
            } catch (ObjectNotFound) {
                // ignored
            } catch (Throwable $e) {
                captureException($e);
            } finally {
                gc_collect_cycles();
            }
        });
    }
}
