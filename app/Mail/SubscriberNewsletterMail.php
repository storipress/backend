<?php

namespace App\Mail;

use App\Enums\Email\EmailUserType;
use App\Models\Tenants\Article;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Carbon;

class SubscriberNewsletterMail extends SubscriberMailable
{
    /**
     * Create a new message instance.
     *
     * @param  array{url: string, caption?:string}|null  $cover
     * @param  array{name: string, avatar: string}|null  $author
     * @return void
     */
    public function __construct(
        protected int $subscriberId,
        protected string $url,
        protected int $articleId,
        protected string $title,
        protected ?string $blurb,
        protected Carbon $published_at,
        protected ?array $cover,
        protected ?array $author,
        protected string $content,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function target(): array
    {
        return [
            'target_id' => $this->articleId,
            'target_type' => Article::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function id(): int
    {
        return 34133852;
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => sprintf('<%s>', $this->unsubscribeUrl()),
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return array_merge(parent::data(), [
            'article_url' => $this->url,
            'title' => $this->title,
            'blurb' => $this->blurb,
            'cover' => $this->cover,
            'author' => $this->author,
            'published_at' => $this->published_at->format('M j'),
            'content' => $this->content,
            'share_url' => sprintf('https://twitter.com/intent/tweet?url=%s', $this->url),
            'unsubscribe_url' => $this->unsubscribeUrl(),
        ]);
    }

    /**
     * Generate unsubscribe url.
     */
    protected function unsubscribeUrl(): string
    {
        $data = [
            'user_type' => EmailUserType::subscriber()->value,
            'user_id' => $this->subscriberId,
            'tenant' => $this->client,
        ];

        return route('unsubscribe-from-mailing-list', [
            'payload' => encrypt($data),
        ]);
    }
}
