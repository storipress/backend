<?php

namespace App\Mail;

class UserInviteMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $inviter,
        protected string $email,
        protected bool $exist = false,
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
        return 27796821;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return [
            'publication' => $this->publication,
            'site_url' => $this->siteUrl(),
            'inviter' => $this->inviter,
            'action_url' => $this->actionUrl(
                sprintf(
                    '/auth/%s',
                    $this->exist ? 'login' : 'signup',
                ),
                queries: [
                    'email' => $this->email,
                    'source' => 'invitation',
                    'client' => (string) $this->client,
                ],
            ),
        ];
    }
}
