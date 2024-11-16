<?php

namespace App\Mail;

class SubscriberEmailVerifyMail extends SubscriberMailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $name,
        protected string $link,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function id(): int
    {
        return 27726474;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return array_merge(parent::data(), [
            'name' => $this->name,
            'action_url' => $this->link,
        ]);
    }
}
