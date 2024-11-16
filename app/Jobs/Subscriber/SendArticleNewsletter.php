<?php

namespace App\Jobs\Subscriber;

use App\Enums\Article\Plan;
use App\Events\WebhookPushing;
use App\Mail\SubscriberNewsletterMail;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use App\Models\Tenants\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Segment\Segment;
use Sentry\State\Scope;
use Webmozart\Assert\InvalidArgumentException;

use function Sentry\captureException;
use function Sentry\withScope;

class SendArticleNewsletter implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        public int $articleId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Tenant::find($this->tenantId)?->run(function (Tenant $tenant) {
            $article = Article::with('authors')->find($this->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            if (!$article->published) {
                return;
            }

            if ($article->newsletter_at !== null) {
                return;
            }

            $article->update([
                'newsletter' => true,
                'newsletter_at' => now(),
            ]);

            $author = $article->authors->first();

            if ($author instanceof User) {
                $author = [
                    'name' => $author->full_name ?: $tenant->name,
                    'avatar' => $author->avatar,
                ];
            }

            try {
                $html = app('prosemirror')->toNewsletter(
                    $article->document['default'],
                );
            } catch (InvalidArgumentException) {
                $html = $article->html;
            }

            if (empty($html)) {
                withScope(function (Scope $scope) use ($tenant, $article) {
                    $scope->setTag('tenant', $tenant->id);

                    $scope->setTag('article.id', (string) $article->id);

                    captureException(
                        new RuntimeException('Empty article body when sending newsletter.'),
                    );
                });

                return;
            }

            if (isset($article->document['title'])) {
                $title = app('prosemirror')->toPlainText(
                    $article->document['title'],
                );
            }

            if (!isset($title)) {
                $title = htmlspecialchars_decode(strip_tags($article->title));
            }

            $payload = [
                'plan' => $article->plan,
                'url' => $article->url,
                'articleId' => $article->id,
                'title' => $title,
                'blurb' => $article->blurb,
                'published_at' => $article->published_at,
                'cover' => (isset($article->cover['url']) && is_not_empty_string($article->cover['url'])) ? $article->cover : null,
                'author' => $author,
                'content' => $html,
            ];

            Subscriber::where('newsletter', true)
                ->chunkById(1000, function (Collection $subscribers) use ($payload) {
                    /** @var Collection<int, Subscriber> $subscribers */
                    foreach ($subscribers as $subscriber) {
                        if ($subscriber->bounced) {
                            continue;
                        }

                        if (Plan::subscriber()->is($payload['plan']) && !($subscriber->subscribed() || $subscriber->subscribed('manual'))) {
                            continue;
                        }

                        Mail::to($subscriber->email)->send(
                            new SubscriberNewsletterMail(
                                subscriberId: $subscriber->id,
                                url: $payload['url'],
                                articleId: $payload['articleId'],
                                title: $payload['title'],
                                blurb: $payload['blurb'],
                                published_at: $payload['published_at'], // @phpstan-ignore-line
                                cover: $payload['cover'],
                                author: $payload['author'],
                                content: $payload['content'],
                            ),
                        );
                    }
                });

            Segment::track([
                'userId' => (string) $tenant->owner->id,
                'event' => 'tenant_newsletter_sent',
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_article_uid' => $article->id,
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);

            WebhookPushing::dispatch($tenant->id, 'article.newsletter.sent', $article);
        });
    }
}
