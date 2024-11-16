<?php

namespace App\Listeners\Partners\Shopify\ContentPulling;

use App\Enums\Article\PublishType;
use App\Enums\AutoPosting\State;
use App\Events\Partners\Shopify\ArticlesSynced as ShopifyArticlesSynced;
use App\Events\Partners\Shopify\ContentPulling;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Mail\Partners\Shopify\PullArticlesFailureMail;
use App\Mail\Partners\Shopify\PullArticlesResultMail;
use App\Mail\Partners\Shopify\PullArticlesStartMail;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Stage;
use App\Models\Tenants\Tag;
use App\Observers\TriggerSiteRebuildObserver;
use App\Observers\WebhookPushingObserver;
use App\Queue\Middleware\WithoutOverlapping;
use App\SDK\Shopify\Shopify;
use App\Sluggable;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Segment\Segment;
use Sentry\State\Scope;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;
use function Sentry\withScope;

class SyncBlogArticles implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected readonly Shopify $app) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(ContentPulling $event): array
    {
        return [(new WithoutOverlapping($event->tenantId))->dontRelease()];
    }

    public function handle(ContentPulling $event): void
    {
        $tenant = Tenant::with('owner')
            ->where('id', '=', $event->tenantId)
            ->sole();

        Mail::to($tenant->owner->email)->send(
            new PullArticlesStartMail(),
        );

        Segment::track([
            'userId' => (string) $tenant->owner->id,
            'event' => 'tenant_shopify_syncing',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);

        /** @var int|Throwable $result */
        $result = $tenant->run(function () use ($tenant) {
            $count = 0;

            try {
                $this->disableEvents();

                $integration = Integration::where('key', 'shopify')->sole();

                $configuration = $integration->internals ?: [];

                $data = $integration->data;

                /** @var string|null $domain */
                $domain = Arr::get($configuration, 'domain');

                /** @var string|null $myshopifyDomain */
                $myshopifyDomain = Arr::get($configuration, 'myshopify_domain');

                if (! $domain || ! $myshopifyDomain) {
                    throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
                }

                $prefix = Arr::get($data, 'prefix', Arr::get($configuration, 'prefix'));

                if (! $prefix) {
                    throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
                }

                /** @var string|null $token */
                $token = Arr::get($configuration, 'access_token');

                if (! $token) {
                    throw new ErrorException(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
                }

                // find the articles that have shopify id
                $syncedIds = ArticleAutoPosting::where('platform', 'shopify')
                    ->pluck('target_id')
                    ->toArray();

                $defaultId = Stage::default()->sole()->id;

                $readyId = Stage::ready()->sole()->id;

                $prosemirror = app('prosemirror');

                $this->app->setShop($myshopifyDomain);

                $this->app->setAccessToken($token);

                $blogs = $this->app->getBlogs();

                foreach ($blogs as $blog) {
                    $desk = $this->createDesk($blog['id'], $blog['title'], Sluggable::slug($blog['handle']));

                    $articles = $this->app->getArticles($blog['id']);

                    Assert::isArray($articles);

                    foreach ($articles as $article) {
                        try {
                            $targetId = sprintf('%s_%s', $blog['id'], $article['id']);

                            if (in_array($targetId, $syncedIds)) {
                                continue;
                            }

                            // compatible with old version
                            if (in_array($article['id'], $syncedIds)) {
                                continue;
                            }

                            $model = new Article([
                                'encryption_key' => base64_encode(random_bytes(32)),
                                'desk_id' => $desk->id,
                            ]);

                            $published = $article['published_at'] !== null;

                            $model->title = $article['title'];

                            $cover = Arr::get($article, 'image.src');

                            Assert::nullOrString($cover);

                            $model->slug = SlugService::createSlug(Article::class, 'slug', Sluggable::slug($article['handle']));

                            $model->cover = $cover !== null ? ['alt' => '', 'caption' => '', 'url' => $cover] : null;

                            $model->stage_id = $article['published_at'] === null ? $defaultId : $readyId;

                            $model->publish_type = $published ? PublishType::immediate() : PublishType::none();

                            $model->published_at = $published ? Carbon::parse($article['published_at'])->timestamp : null; // @phpstan-ignore-line

                            if (! empty($article['body_html'])) {
                                $html = $prosemirror->rewriteHTML($article['body_html']);

                                Assert::notNull($html);

                                $model->html = $html;

                                $content = $prosemirror->toProseMirror($html);
                            }

                            $blurbPlain = null;

                            $summary = $article['summary_html'];

                            if (! empty($summary)) {
                                $summaryHtml = $prosemirror->rewriteHTML($summary);

                                Assert::notNull($summaryHtml);

                                $blurb = $prosemirror->toProseMirror($summaryHtml);

                                Assert::notNull($blurb);

                                $blurbPlain = $prosemirror->toPlainText($blurb);

                                Assert::notNull($blurbPlain);

                                $model->blurb = $blurbPlain;
                            }

                            $emptyDoc = [
                                'type' => 'doc',
                                'content' => [],
                            ];

                            $content = $content ?? $emptyDoc;

                            $model->document = [
                                'default' => $content,
                                'title' => $prosemirror->toProseMirror($article['title'] ?: '') ?: $emptyDoc,
                                'blurb' => $prosemirror->toProseMirror($blurbPlain ?: '') ?: $emptyDoc,
                                'annotations' => [],
                            ];

                            $model->seo = [
                                'og' => [
                                    'title' => '',
                                    'description' => '',
                                ],
                                'meta' => [
                                    'title' => '',
                                    'description' => '',
                                ],
                                'hasSlug' => true,
                                'ogImage' => '',
                            ];

                            $model->plaintext = $prosemirror->toPlainText($content);

                            $model->save();

                            $model->autoPostings()->create([
                                'state' => State::posted(),
                                'platform' => 'shopify',
                                'domain' => $domain,
                                'prefix' => $prefix,
                                'pathname' => sprintf('/posts/%s', $model->slug),
                                'target_id' => $targetId,
                            ]);

                            $count++;

                            $model->authors()->syncWithoutDetaching($tenant->owner);

                            if (empty($article['tags'])) {
                                continue;
                            }

                            $tags = explode(', ', $article['tags']);

                            $collection = new Collection();

                            // attach tags
                            foreach ($tags as $tag) {
                                $collection->add($this->createTag(trim($tag)));
                            }

                            $model->tags()->syncWithoutDetaching($collection);

                        } catch (Throwable $e) {
                            withScope(function (Scope $scope) use ($e, $article, $blog): void {
                                $scope->setContext('debug', [
                                    'id' => $article['id'],
                                    'blog_id' => $blog['id'],
                                    'title' => $article['title'],
                                    'handle' => $article['handle'],
                                ]);

                                captureException($e);
                            });
                        }
                    }
                }
            } catch (Throwable $e) {
                return $e;
            } finally {
                $this->enableEvents();

                Article::makeAllSearchable(100);
            }

            return $count;
        });

        if ($result instanceof Throwable) {
            Mail::to($tenant->owner->email)->send(
                new PullArticlesFailureMail(),
            );

            withScope(function (Scope $scope) use ($result, $event): void {
                $scope->setContext('debug', [
                    'tenant' => $event->tenantId,
                    'platform' => 'shopify',
                    'action' => 'pull_articles',
                ]);

                captureException($result);
            });

            return;
        }

        Mail::to($tenant->owner->email)->send(
            new PullArticlesResultMail($result),
        );

        Segment::track([
            'userId' => (string) $tenant->owner->id,
            'event' => 'tenant_shopify_synced',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'imported_articles' => $result,
            ],
            'context' => [
                'groupId' => $tenant->name,
            ],
        ]);

        ShopifyArticlesSynced::dispatch($event->tenantId);
    }

    protected function createDesk(int $id, string $name, string $slug): Desk
    {
        return Desk::firstOrCreate(['slug' => $slug], ['name' => $name, 'shopify_id' => $id]);
    }

    protected function createTag(string $name): Tag
    {
        return Tag::firstOrCreate(['name' => $name]);
    }

    /**
     * Prevent trigger a lot of model events.
     */
    protected function disableEvents(): void
    {
        TriggerSiteRebuildObserver::mute();

        WebhookPushingObserver::mute();

        Article::disableSearchSyncing();
    }

    /**
     * Enable model events.
     */
    protected function enableEvents(): void
    {
        TriggerSiteRebuildObserver::unmute();

        WebhookPushingObserver::unmute();

        Article::enableSearchSyncing();
    }
}
