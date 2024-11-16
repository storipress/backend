<?php

namespace App\Mail;

use Illuminate\Support\Carbon;

class UserMigrationInviteMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $inviter,
        protected string $email,
        protected string $token,
        protected Carbon $expire_on,
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
        $url = $this->actionUrl(
            path: '/auth/password/create',
            queries: [
                'email' => $this->email,
                'token' => $this->token,
                'expire_on' => (string) $this->expire_on->timestamp,
            ],
        );

        return [
            'publication' => $this->publication,
            'site_url' => $this->siteUrl(),
            'inviter' => $this->inviter,
            'action_url' => sprintf('%s&expired_at=%d', $url, $this->expire_on->timestamp),
        ];
    }
}
