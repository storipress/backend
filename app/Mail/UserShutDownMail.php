<?php

namespace App\Mail;

class UserShutDownMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $name,
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
        return 37172482;
    }

    /**
     * {@inheritdoc}
     */
    protected function sender(): array
    {
        return ['hello@storipress.com', 'Storipress'];
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
