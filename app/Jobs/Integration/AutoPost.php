<?php

namespace App\Jobs\Integration;

use App\Console\Schedules\Weekly\RefreshTwitterProfile;
use App\Enums\AutoPosting\State;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Notifications\Facebook\FacebookUnauthorizedNotification;
use App\Notifications\Twitter\TwitterUnauthorizedNotification;
use App\SDK\SocialPlatformsInterface;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Storipress\Facebook\Exceptions\ExpiredAccessToken as ExpiredFacebookAccessToken;
use Storipress\Twitter\Exceptions\ExpiredAccessToken as ExpiredTwitterAccessToken;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

/**
 * @phpstan-import-type FacebookConfiguration from Integration
 * @phpstan-import-type TwitterConfiguration from Integration
 *
 * @phpstan-type SocialPath array{
 *    domain: string,
 *    prefix: string|null,
 *    pathname: string
 * }| array{}
 */
class AutoPost implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 180;

    /**
     * @var Tenant|null
     */
    protected $tenant;

    /**
     * Integration enable table
     *
     * @var bool[]
     */
    protected $enable = [
        'facebook' => true,
        'twitter' => true,
        'linkedin' => false,
    ];

    /**
     * @var string
     */
    protected $postId = '';

    /**
     * @var SocialPath
     */
    protected $path = [];

    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var ArticleAutoPosting|null
     */
    protected $articleAutoPosting = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantKey,
        public int $articleId,
        public string $platform,
        protected ?SocialPlatformsInterface $client = null,
    ) {}

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->tenantKey.'_'.$this->articleId.'_'.$this->platform;
    }

    /**
     * @throws Throwable
     */
    public function failed(Throwable $exception): void
    {
        $this->slackLog(
            'debug',
            '[Auto Post] AutoPost failed',
            [
                'exception' => $exception->getMessage(),
                'job_id' => $this->uniqueId(),
            ],
        );

        $this->articleAutoPosting?->update([
            'state' => State::aborted(),
            'data' => [
                'message' => $exception->getMessage(),
            ],
        ]);

        throw $exception;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantKey);

        Assert::isInstanceOf($tenant, Tenant::class);

        if (app()->environment('development') && $tenant->user_id === 17) {
            return;
        }

        tenancy()->initialize($tenant);

        $this->tenant = $tenant;

        /** @var ArticleAutoPosting|null $articleAutoPosting */
        $articleAutoPosting = ArticleAutoPosting::where('article_id', $this->articleId)
            ->where('platform', $this->platform)
            ->where('state', State::waiting())
            ->first();

        if ($articleAutoPosting === null) {
            return;
        }

        $this->articleAutoPosting = $articleAutoPosting;

        $article = Article::find($this->articleId);

        if ($article === null) {
            $this->articleAutoPosting->update([
                'state' => State::aborted(),
                'data' => [
                    'message' => 'Can not find the article data.',
                ],
            ]);

            return;
        }

        Assert::isInstanceOf($article, Article::class);

        $this->post($this->platform, $article);
    }

    /**
     * run auto post
     */
    protected function post(string $platform, Article $article): void
    {
        $integration = Integration::find($platform);

        Assert::isInstanceOf($integration, Integration::class);

        $settings = $article->auto_posting ?: [];

        $internals = $integration->internals ?: [];

        $published = false;

        $link = $article->url;

        if (empty($this->enable[$platform])) {
            $this->articleAutoPosting?->update([
                'state' => State::aborted(),
                'data' => [
                    'message' => '[Auto Post] the settings in AutoPost.php is disabled.',
                ],
            ]);

            return;
        }

        if (empty($settings[$platform])) {
            $this->slackLog(
                'debug',
                '[Auto Post] Unexpected error: empty article auto posting settings',
                [
                    'client' => $this->tenantKey,
                    'article' => $this->articleId,
                    'platform' => $platform,
                ],
            );

            $this->articleAutoPosting?->update([
                'state' => State::aborted(),
                'data' => [
                    'message' => $this->message,
                ],
            ]);

            return;
        }

        $success = $this->fetchArticle($article->id, $article->slug);

        // the article url is not ready
        if (! $success) {
            $this->articleAutoPosting?->update([
                'state' => State::aborted(),
                'data' => [
                    'message' => 'The article url is not ready.',
                ],
            ]);

            return;
        }

        $setting = $settings[$platform];

        if ($platform === 'facebook') {
            /** @var array{page_id: string, text: string, enable: bool} $setting */
            /** @var FacebookConfiguration $internals */
            $published = $this->publishFacebookPost(
                $setting,
                $internals,
                $link,
            );
        } elseif ($platform === 'twitter') {
            /** @var array{user_id: string, text: string, enable: bool} $setting */
            /** @var TwitterConfiguration $internals */
            $published = $this->createTwitterTweets(
                $setting,
                $internals,
                $link,
            );
        }

        if (! $published) {
            $this->articleAutoPosting?->update([
                'state' => State::aborted(),
                'data' => [
                    'message' => $this->message,
                ],
            ]);

            return;
        }

        $this->articleAutoPosting?->update([
            'state' => State::posted(),
            'domain' => $this->path['domain'] ?? null,
            'prefix' => $this->path['prefix'] ?? null,
            'pathname' => $this->path['pathname'] ?? null,
            'target_id' => $this->postId,
        ]);
    }

    /**
     * Publish a facebook post
     *
     * @param array{
     *     page_id: string,
     *     text: string,
     *     enable: bool,
     * } $facebook
     * @param  FacebookConfiguration  $internals
     */
    protected function publishFacebookPost(array $facebook, array $internals, string $link): bool
    {
        $secret = config('services.facebook.client_secret');

        if (! is_not_empty_string($secret)) {
            return false;
        }

        if (! isset($internals['pages'][$facebook['page_id']])) {
            return false; // @todo - facebook 使用者選擇了一個未授權的 page_id
        }

        $page = $internals['pages'][$facebook['page_id']];

        try {
            $feed = app('facebook')
                ->setDebug('warning')
                ->setSecret($secret)
                ->setPageToken($page['access_token'])
                ->feed()
                ->create($page['page_id'], [
                    'message' => $facebook['text'],
                    'link' => $link,
                ]);
        } catch (ExpiredFacebookAccessToken) {
            $tenant = tenant_or_fail();

            $tenant->owner->notify(
                new FacebookUnauthorizedNotification(
                    $tenant->id,
                    $tenant->name,
                ),
            );

            $tenant->update(['facebook_data' => null]);

            Integration::find('facebook')?->reset();

            return false;
        } catch (Throwable $e) {
            captureException($e);

            return false;
        }

        [$pageId, $postId] = explode('_', $feed->id);

        $this->postId = $feed->id;

        $this->path = $this->getFacebookPostPath($postId);

        return true;
    }

    /**
     * @param array{
     *     user_id: string,
     *     text: string,
     *     enable: bool
     * } $twitter
     * @param  TwitterConfiguration  $internals
     *
     * @throws RequestException
     */
    protected function createTwitterTweets(array $twitter, array $internals, string $link): bool
    {
        $tenant = tenant_or_fail();

        $secret = config('services.twitter.client_secret');

        if (! is_not_empty_string($secret)) {
            return false;
        }

        if ($twitter['user_id'] !== $internals['user_id']) {
            return false; // @todo - twitter 使用者選擇了一個未授權的 user_id
        }

        if (Carbon::createFromTimestamp($internals['expires_on'])->isPast()) {
            Artisan::call(RefreshTwitterProfile::class, [
                '--tenants' => [$tenant->id],
            ]);

            $internals = Integration::find('twitter')?->internals;

            if (empty($internals)) {
                return false;
            }
        }

        try {
            $tweet = app('twitter')
                ->setToken($internals['access_token'])
                ->tweet()
                ->create([
                    'text' => Str::of($twitter['text'])
                        ->limit(255, '') // @link https://developer.twitter.com/en/docs/counting-characters
                        ->newLine(2)
                        ->append($link),
                ]);
        } catch (ExpiredTwitterAccessToken) {
            $tenant->owner->notify(
                new TwitterUnauthorizedNotification(
                    $tenant->id,
                    $tenant->name,
                ),
            );

            $tenant->update(['twitter_data' => null]);

            Integration::find('twitter')?->reset();

            return false;
        } catch (Throwable $e) {
            captureException($e);

            return false;
        }

        $this->postId = $tweet->id;

        $this->path = $this->getTwitterPostPath($internals['user_id'], $tweet->id);

        return true;
    }

    /**
     * @return SocialPath
     */
    protected function getFacebookPostPath(string $postId): array
    {
        return [
            'domain' => 'www.facebook.com',
            'prefix' => null,
            'pathname' => sprintf('/%s', $postId),
        ];
    }

    /**
     * @return SocialPath
     */
    protected function getTwitterPostPath(string $userId, string $postId): array
    {
        return [
            'domain' => 'twitter.com',
            'prefix' => null,
            'pathname' => sprintf('/%s/status/%s', $userId, $postId),
        ];
    }

    /**
     * @param  array<mixed>  $contents
     */
    protected function slackLog(string $type, string $message, array $contents): void
    {
        $this->message = $message;

        // Don't notify if the environment is 'testing' or 'local'
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        if (! in_array($type, ['error', 'debug'])) {
            $type = 'debug';
        }

        Log::channel('slack')->$type(
            $message,
            array_merge(['env' => app()->environment()], $contents),
        );
    }

    protected function fetchArticle(int $id, string $slug): bool
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string $tenantId */
        $tenantId = $tenant->getKey();

        $rawSlug = rawurlencode($slug);

        $domains = [
            'page' => $tenant->cf_pages_domain,
            'normal' => $tenant->url,
        ];

        foreach ($domains as $type => $domain) {
            $url = sprintf('https://%s/posts/%s', $domain, $rawSlug);

            try {
                $response = Http::get($url);
            } catch (TooManyRedirectsException) {
                return false;
            } catch (Throwable $e) {
                if (! Str::contains($e->getMessage(), 'SSL')) {
                    captureException($e);
                }

                return false;
            }

            $filename = base_path(
                sprintf(
                    'storage/temp/%s-%d-%s-%d.log',
                    $tenantId,
                    $id,
                    $type,
                    now()->getTimestampMs(),
                ),
            );

            $content = [
                $url,
                $response->status(),
            ];

            foreach ($response->headers() as $name => $values) {
                foreach ($values as $value) {
                    $content[] = sprintf('%s: %s', $name, $value);
                }
            }

            $content[] = $response->body();

            file_put_contents($filename, implode(PHP_EOL, $content));
        }

        return true;
    }
}
