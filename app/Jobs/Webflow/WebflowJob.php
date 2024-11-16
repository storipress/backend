<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\Webflow;
use App\Notifications\Webflow\WebflowSyncFailedNotification;
use App\Notifications\Webflow\WebflowUnauthorizedNotification;
use App\Queue\Middleware\WithoutOverlapping;
use Generator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Storipress\Webflow\Exceptions\HttpHitRateLimit;
use Storipress\Webflow\Exceptions\HttpUnauthorized;
use Storipress\Webflow\Objects\Item;
use Throwable;

use function Sentry\captureException;
use function Sentry\configureScope;

abstract class WebflowJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 1;

    /**
     * The name of the rate limiter.
     */
    public string $rateLimiterName = 'webflow-api-general';

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new RateLimited($this->rateLimiterName))
                ->dontRelease(),

            (new WithoutOverlapping($this->overlappingKey()))
                ->dontRelease(),

            (new ThrottlesExceptionsWithRedis(1, 1))
                ->backoff(1)
                ->by($this->throttlingKey())
                ->when(fn (Throwable $throwable) => $throwable instanceof HttpHitRateLimit),

            (new ThrottlesExceptionsWithRedis(1, 10))
                ->backoff(10)
                ->by(sprintf('webflow:%s:unauthorized', $this->tenantId ?? 'none'))
                ->when(fn (Throwable $throwable) => $throwable instanceof HttpUnauthorized),
        ];
    }

    /**
     * The key of the rate limit.
     */
    abstract public function rateLimitingKey(): string;

    /**
     * The job's unique key used for preventing overlaps.
     */
    abstract public function overlappingKey(): string;

    /**
     * The developer specified key that the rate limiter should use.
     */
    abstract public function throttlingKey(): string;

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $e): void
    {
        $ignores = [
            'afterCommit',
            'batch_id',
            'chainCatchCallbacks',
            'chainConnection',
            'chainQueue',
            'chained',
            'connection',
            'delay',
            'fake_batch',
            'item',
            'job',
            'middleware',
            'queue',
            'rateLimiterName',
            'tries',
        ];

        $data = array_diff_key(
            get_object_vars($this), // get all public properties
            array_fill_keys($ignores, true),
        );

        // convert array key from camelCase to snake_case
        $data = Arr::mapWithKeys($data, fn ($val, $key) => [Str::snake($key) => $val]);

        $data['message'] = $e->getMessage();

        $data['trace'] = $e->getTraceAsString();

        configureScope(function (Scope $scope) use ($data) {
            $scope->setContext('webflow', $data);
        });

        captureException($e);

        if (! isset($this->tenantId)) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        if ($e instanceof HttpUnauthorized) {
            $tenant->owner->notify(
                new WebflowUnauthorizedNotification(
                    $tenant->id,
                    $tenant->name,
                ),
            );

            $tenant->run(function () {
                Webflow::retrieve()->config->update(['expired' => true]);
            });

            return;
        }

        $tenant->owner->notify(
            new WebflowSyncFailedNotification(
                $tenant->id,
                $tenant->name,
                $data,
            ),
        );
    }

    /**
     * @return Generator<int, Item>
     */
    public function items(string $collectionId): Generator
    {
        $api = app('webflow')->item();

        if (isset($this->webflowId)) {
            yield $api->get($collectionId, $this->webflowId);
        } else {
            $offset = 0;

            $limit = 100;

            do {
                [
                    'data' => $items,
                    'pagination' => $pagination,
                ] = $api->list(
                    $collectionId,
                    $offset,
                    $limit,
                );

                foreach ($items as $item) {
                    yield $item;
                }

                $offset += $limit;
            } while ($offset < $pagination->total);
        }
    }
}
