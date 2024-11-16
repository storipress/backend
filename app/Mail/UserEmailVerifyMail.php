<?php

namespace App\Mail;

class UserEmailVerifyMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $email,
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
        return 27682762;
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
            'email' => $this->email,
            'action_url' => $this->actionUrl(
                path: '/auth/confirm-email',
                queries: [
                    'email' => $this->email,
                    'expire_on' => (string) now()->addDay()->timestamp,
                ],
            ),
        ];
    }
}
