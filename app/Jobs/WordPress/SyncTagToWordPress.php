<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\Tag;
use App\Notifications\WordPress\WordPressDatabaseDieNotification;
use App\Notifications\WordPress\WordPressRouteNotFoundNotification;
use Storipress\WordPress\Exceptions\CannotCreateException;
use Storipress\WordPress\Exceptions\CannotUpdateException;
use Storipress\WordPress\Exceptions\DuplicateTermSlugException;
use Storipress\WordPress\Exceptions\ForbiddenException;
use Storipress\WordPress\Exceptions\NotFoundException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Storipress\WordPress\Exceptions\TermExistsException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Exceptions\WpDieException;
use Storipress\WordPress\Objects\Tag as TagObject;
use Throwable;

use function Sentry\captureException;

class SyncTagToWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $tagId = null,
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
            $this->tagId ?: 'all',
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

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            $error = 0;

            $query = Tag::withTrashed()->withoutEagerLoads();

            if ($this->tagId) {
                $query->where('id', '=', $this->tagId);
            }

            if ($this->skipSynced) {
                $query->whereNull('wordpress_id');
            }

            foreach ($query->lazyById() as $tag) {
                if ($tag->trashed()) {
                    if ($tag->wordpress_id !== null) {
                        try {
                            app('wordpress')->tag()->delete($tag->wordpress_id);
                        } catch (WordPressException) {
                            // ignored
                        }

                        $tag->update([
                            'wordpress_id' => null,
                        ]);
                    }

                    continue;
                }

                $params = [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description,
                ];

                $termId = $wpTag = null;

                try {
                    $wpTag = $this->createOrUpdateTag($tag->wordpress_id, $params);
                } catch (DuplicateTermSlugException) {
                    try {
                        $wpTag = $this->createOrUpdateTag(null, $params);
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
                    $tag->update([
                        'wordpress_id' => $termId,
                    ]);
                } elseif ($wpTag) {
                    $tag->update([
                        'wordpress_id' => $wpTag->id,
                    ]);
                }

                ingest(
                    data: [
                        'name' => 'wordpress.tag.sync',
                        'source_type' => 'tag',
                        'source_id' => $this->tagId,
                        'wordpress_id' => $tag->wordpress_id,
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
     *     description: ?string,
     * } $params
     *
     * @throws WordPressException
     */
    public function createOrUpdateTag(?int $id, array $params): TagObject
    {
        $api = app('wordpress')->tag();

        if (is_int($id)) {
            return $api->update($id, $params);
        }

        return $api->create($params);
    }
}
