<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\WordPress;
use App\Notifications\WordPress\WordPressDatabaseDieNotification;
use App\Notifications\WordPress\WordPressRouteNotFoundNotification;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Storipress\WordPress\Exceptions\CannotCreateException;
use Storipress\WordPress\Exceptions\CannotUpdateException;
use Storipress\WordPress\Exceptions\DuplicateTermSlugException;
use Storipress\WordPress\Exceptions\ForbiddenException;
use Storipress\WordPress\Exceptions\NotFoundException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Storipress\WordPress\Exceptions\TermExistsException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Exceptions\WpDieException;
use Storipress\WordPress\Objects\Category;
use Throwable;

use function Sentry\captureException;

class SyncDeskToWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $deskId = null,
        public bool $skipSynced = false,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->deskId ?: 'all',
        );
    }

    /**
     * Handle the given event.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            $error = 0;

            $query = Desk::withTrashed()
                ->withoutEagerLoads()
                ->with([
                    'desk' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('wordpress_id')
                            ->select(['id', 'wordpress_id']);
                    },
                ]);

            if ($this->deskId) {
                $query->where('id', '=', $this->deskId);
            }

            if ($this->skipSynced) {
                $query->whereNull('wordpress_id');
            }

            foreach ($query->lazyById() as $desk) {
                if ($desk->trashed()) {
                    if ($desk->wordpress_id !== null) {
                        try {
                            app('wordpress')->category()->delete($desk->wordpress_id);
                        } catch (WordPressException) {
                            // ignored
                        }

                        $desk->update([
                            'wordpress_id' => null,
                        ]);
                    }

                    continue;
                }

                $params = [
                    'name' => $desk->name,
                    'slug' => $desk->slug,
                    'description' => $desk->description,
                ];

                // avoid overwriting an unsynchronized parent.
                if ($desk->desk?->wordpress_id) {
                    $params['parent'] = $desk->desk->wordpress_id;
                }

                $termId = $category = null;

                try {
                    $category = $this->createOrUpdateCategory($desk->wordpress_id, $params);
                } catch (DuplicateTermSlugException) {
                    try {
                        $category = $this->createOrUpdateCategory(null, $params);
                    } catch (TermExistsException $e) {
                        $termId = $e->getTermId();
                    }
                } catch (TermExistsException $e) {
                    $termId = $e->getTermId();
                } catch (
                    CannotCreateException|
                    CannotUpdateException|
                    NotFoundException|
                    RestForbiddenException|
                    ForbiddenException
                ) {
                    $wordpress->config->update(['expired' => true]);

                    $tenant->owner->notify(
                        new WordPressRouteNotFoundNotification(
                            $tenant->id,
                            $tenant->name,
                        ),
                    );

                    break;
                } catch (WpDieException) {
                    $tenant->owner->notify(
                        new WordPressDatabaseDieNotification(
                            $tenant->id,
                            $tenant->name,
                        ),
                    );

                    break;
                } catch (Throwable $e) {
                    captureException($e);

                    if ((++$error) === 5) {
                        break;
                    }

                    continue;
                }

                if ($termId) {
                    $desk->update([
                        'wordpress_id' => $termId,
                    ]);
                } elseif ($category) {
                    $desk->update([
                        'wordpress_id' => $category->id,
                    ]);
                }

                ingest(
                    data: [
                        'name' => 'wordpress.desk.sync',
                        'source_type' => 'desk',
                        'source_id' => $this->deskId,
                        'wordpress_id' => $desk->wordpress_id,
                    ],
                    type: 'action',
                );
            }
        });
    }

    /**
     * @param array{
     *     name: string,
     *     slug: string,
     *     parent?: int|null,
     *     description: ?string,
     * } $params
     *
     * @throws WordPressException
     */
    public function createOrUpdateCategory(?int $id, array $params): Category
    {
        $api = app('wordpress')->category();

        if (is_int($id)) {
            return $api->update($id, $params);
        }

        return $api->create($params);
    }
}
