<?php

namespace App\Mail;

class UserProphetWelcomeMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $first_name,
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
        return 35345005;
    }

    /**
     * {@inheritdoc}
     */
    protected function sender(): array
    {
        return ['alex@storipress.com', 'Storipress'];
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return [
            'first_name' => $this->first_name,
        ];
    }
}
