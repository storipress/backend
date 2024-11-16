<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\User;
use App\Notifications\WordPress\WordPressDatabaseDieNotification;
use App\Notifications\WordPress\WordPressRouteNotFoundNotification;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Storipress\WordPress\Exceptions\CannotCreateException;
use Storipress\WordPress\Exceptions\CannotEditOthersException;
use Storipress\WordPress\Exceptions\CannotUpdateException;
use Storipress\WordPress\Exceptions\DuplicateTermSlugException;
use Storipress\WordPress\Exceptions\ForbiddenException;
use Storipress\WordPress\Exceptions\InvalidAuthorIdException;
use Storipress\WordPress\Exceptions\InvalidPostIdException;
use Storipress\WordPress\Exceptions\NotFoundException;
use Storipress\WordPress\Exceptions\PostAlreadyTrashedException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Storipress\WordPress\Exceptions\TermExistsException;
use Storipress\WordPress\Exceptions\UnexpectedValueException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Exceptions\WpDieException;
use Storipress\WordPress\Objects\Post;
use Throwable;

use function Sentry\captureException;

class SyncArticleToWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $articleId = null,
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
            $this->articleId ?: 'all',
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

            $query = Article::withTrashed()
                ->withoutEagerLoads()
                ->with([
                    'authors' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('wordpress_id')
                            ->select(['id', 'wordpress_id']);
                    },
                    'desk' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->select(['id', 'wordpress_id']);
                    },
                    'tags' => function (Builder $query) {
                        $query->withoutEagerLoads()
                            ->whereNotNull('wordpress_id')
                            ->select(['id', 'wordpress_id']);
                    },
                ]);

            if ($this->articleId) {
                $query->where('id', '=', $this->articleId);
            }

            if ($this->skipSynced) {
                $query->whereNull('wordpress_id');
            }

            foreach ($query->lazyById() as $article) {
                if ($article->trashed()) {
                    if ($article->wordpress_id !== null) {
                        try {
                            app('wordpress')->post()->delete($article->wordpress_id);
                        } catch (WordPressException) {
                            // ignored
                        }

                        $article->update([
                            'wordpress_id' => null,
                        ]);
                    }

                    continue;
                }

                // sync desk if not exists on WordPress
                if ($article->desk->wordpress_id === null) {
                    SyncDeskToWordPress::dispatchSync($this->tenantId, $article->desk->id);

                    $article->desk->refresh();
                }

                $document = $article->document['default'];

                $html = app('prosemirror')->escapeHTML($document, [
                    'client_id' => $this->tenantId,
                    'article_id' => $article->id,
                ]);

                $script = script_tag('wordpress', $tenant->id);

                $content = $html . PHP_EOL . $script;

                $params = [
                    'date' => $article->published_at?->toIso8601String(),
                    'date_gmt' => $article->published_at?->toIso8601String(),
                    'status' => ($article->published || $article->scheduled)
                        ? 'publish'
                        : 'draft',
                    'slug' => $article->slug,
                    'title' => strip_tags($article->title),
                    'content' => trim($content),
                    'excerpt' => strip_tags($article->blurb ?: ''),
                    'author' => $article->authors->first()?->wordpress_id ?: $wordpress->config->user_id,
                    'categories' => $article->desk->wordpress_id ?: [],
                    'tags' => $article->tags->pluck('wordpress_id')->toArray(),
                ];

                try {
                    $post = $this->createOrUpdatePost($article->wordpress_id, $params);
                } catch (PostAlreadyTrashedException) {
                    continue; // ignored
                } catch (DuplicateTermSlugException) {
                    try {
                        $post = $this->createOrUpdatePost(null, $params);
                    } catch (TermExistsException $e) {
                        $article->update([
                            'wordpress_id' => $e->getTermId(),
                        ]);

                        continue;
                    }
                } catch (TermExistsException $e) {
                    $article->update([
                        'wordpress_id' => $e->getTermId(),
                    ]);

                    continue;
                } catch (InvalidAuthorIdException) {
                    $author = $article->authors->first();

                    if (!($author instanceof User)) {
                        continue;
                    }

                    $author->update([
                        'wordpress_id' => null,
                    ]);

                    SyncArticleToWordPress::dispatch($this->tenantId, $article->id);

                    continue;
                } catch (
                    CannotCreateException|
                    CannotUpdateException|
                    CannotEditOthersException|
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

                $article->update([
                    'wordpress_id' => $post->id,
                ]);

                SyncArticleCoverToWordPress::dispatchSync($this->tenantId, $article->id);

                SyncArticleSeoToWordPress::dispatchSync($this->tenantId, $article->id);

                SyncArticleAcfToWordPress::dispatchSync($this->tenantId, $article->id);

                ingest(
                    data: [
                        'name' => 'wordpress.article.sync',
                        'source_type' => 'article',
                        'source_id' => $this->articleId,
                        'wordpress_id' => $article->wordpress_id,
                    ],
                    type: 'action',
                );
            }
        });
    }

    /**
     * @param array{
     *     date: ?string,
     *     date_gmt: ?string,
     *     slug: string,
     *     status: string,
     *     title: string,
     *     content: ?string,
     *     excerpt: string,
     *     author: int,
     *     categories: int[]|int,
     *     tags: array<mixed>,
     * } $params
     *
     * @throws WordPressException
     */
    public function createOrUpdatePost(?int $id, array $params): Post
    {
        $api = app('wordpress')->post();

        if (is_int($id)) {
            try {
                return $api->update($id, $params);
            } catch (InvalidPostIdException) {
                // ignored
            } catch (UnexpectedValueException $e) {
                $ignores = [
                    'wp-scheduled-posts/vendor/facebook/graph-sdk',
                ];

                if (!Str::contains($e->getMessage(), $ignores, true)) {
                    throw $e;
                }
            }
        }

        return $api->create($params);
    }
}
