<?php

namespace App\Mail;

use App\Enums\Email\EmailUserType;
use Illuminate\Mail\Mailables\Headers;

class SubscriberColdEmail extends SubscriberMailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected int $subscriberId,
        protected string $title,
        protected string $content,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function id(): int
    {
        return 35516688;
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
            'subject' => $this->title,
            'content' => $this->content,
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
