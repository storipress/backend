<?php

namespace App\Mail\Partners\Shopify;

use App\Mail\Mailable;

class ReauthorizeMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $firstName,
        protected string $actionUrl,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function server(): string
    {
        return 'app_server_token';
    }

    /**
     * {@inheritdoc}
     */
    protected function id(): int
    {
        return 32851746;
    }

    /**
     * {@inheritdoc}
     */
    protected function sender(): array
    {
        return $this->fromStoripress();
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return [
            'first_name' => $this->firstName,
            'action_url' => $this->actionUrl,
        ];
    }
}
