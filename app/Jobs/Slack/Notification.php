<?php

namespace App\Jobs\Slack;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Stage;
use App\Models\User;
use App\SDK\Slack\Slack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

final class Notification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    protected string $token = '';

    /**
     * @param  array{user_id:int|null, stage:int|null, channels:string[]}  $data
     */
    public function __construct(
        protected string $tenantKey,
        public int $articleId,
        public string $type,
        public array $data,
        protected ?Slack $client = null,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantKey);

        Assert::isInstanceOf($tenant, Tenant::class);

        tenancy()->initialize($tenant);

        $slack = Integration::find('slack');

        $internals = $slack?->internals;

        if (empty($internals)) {
            return;
        }

        /** @var string|null $token */
        $token = Arr::get($internals, 'bot_access_token');

        if (empty($token)) {
            return;
        }

        $this->token = $token;

        match ($this->type) {
            'stage' => $this->stageChangedNotify(),
            'published' => $this->publishedNotify(),
            default => null,
        };
    }

    protected function stageChangedNotify(): void
    {
        $client = $this->client ?? new Slack();

        $article = Article::find($this->articleId);

        Assert::isInstanceOf($article, Article::class);

        /** @var array{slack:array{text:string}|null} $postData */
        $postData = $article->auto_posting;

        $url = $article->edit_url;

        $title = $this->escapeText(html_entity_decode(strip_tags($article->title)));

        $desk = $article->desk->name;

        $authors = $article->authors;

        /** @var string $text */
        $text = Arr::get($postData, 'slack.text', '');

        $userId = Arr::get($this->data, 'user_id');

        /** @var User $user */
        $user = User::find($userId);

        $name = $user->full_name ?: $user->email;

        $authorsName = $authors->pluck('full_name')->filter()->values()->implode(', ');

        if (empty($authorsName)) {
            $authorsName = $authors->pluck('email')->implode(', ');
        }

        $stage = Stage::find($this->data['stage'])?->name;

        $replaces = [
            '{name}' => $name,
            '{url}' => $url,
            '{title}' => $title,
            '{stage}' => $stage,
            '{desk}' => $desk,
            '{authors}' => $authorsName,
            '{note}' => $text,
        ];

        $replaces = Arr::map($replaces, fn (string $value) => addslashes($value));

        $path = empty($text)
            ? resource_path('notifications/slack/stage-changed.json')
            : resource_path('notifications/slack/stage-changed-with-note.json');

        $body = file_get_contents($path);

        if (empty($body)) {
            return;
        }

        $body = strtr($body, $replaces);

        /** @var string $channel */
        foreach ($this->data['channels'] as $channel) {
            $client->postMessage($this->token, $channel, $body);
        }

        $this->recordToArticleAutoPostings($text);
    }

    protected function publishedNotify(): void
    {
        $client = $this->client ?? new Slack();

        $article = Article::find($this->articleId);

        Assert::isInstanceOf($article, Article::class);

        /** @var array{slack:array{text:string}|null} $postData */
        $postData = $article->auto_posting;

        $url = $article->url;

        $title = $this->escapeText(html_entity_decode(strip_tags($article->title)));

        $authors = $article->authors;

        $cover = $article->cover;

        $image = data_get($cover, 'url');

        /** @var string $text */
        $text = Arr::get($postData, 'slack.text', '');

        $authorsName = $authors->pluck('full_name')->filter()->values()->implode(', ');

        if (empty($authorsName)) {
            $authorsName = $authors->pluck('email')->implode(', ');
        }

        $authorsName = Str::replaceLast(', ', ' and ', $authorsName);

        $replaces = [
            '{authors}' => $authorsName,
            '{url}' => $url,
            '{title}' => $title,
            '{note}' => $text,
            '{image}' => $image,
        ];

        $replaces = Arr::map($replaces, fn (string $value) => addslashes($value));

        $path = empty($text)
            ? resource_path('notifications/slack/published.json')
            : resource_path('notifications/slack/published-with-note.json');

        $body = file_get_contents($path);

        if ($body === false) {
            return;
        }

        $body = strtr($body, $replaces);

        // remove image fields if image is null or ''
        if (empty($image)) {
            $body = $this->removeImageFields($body);
        }

        foreach ($this->data['channels'] as $channel) {
            $client->postMessage($this->token, $channel, $body);
        }

        $this->recordToArticleAutoPostings($text);
    }

    protected function removeImageFields(string $body): string
    {
        /** @var array{array{type:string}} $fields */
        $fields = json_decode($body);

        $fields = Arr::where($fields, fn ($field) => $field->type !== 'image');

        $fields = array_values($fields);

        /** @var string $body */
        $body = json_encode($fields);

        return $body;
    }

    protected function recordToArticleAutoPostings(string $note): void
    {
        $autoPosting = new ArticleAutoPosting();

        $autoPosting->article_id = $this->articleId;

        $autoPosting->platform = 'slack';

        $autoPosting->data = [
            'type' => $this->type,
            'note' => $note,
            'data' => $this->data,
        ];

        //5sec
        $autoPosting->save();
    }

    /**
     * @see: https://api.slack.com/reference/surfaces/formatting#escaping
     */
    protected function escapeText(string $string): string
    {
        $replaces = [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
        ];

        return strtr($string, $replaces);
    }
}
