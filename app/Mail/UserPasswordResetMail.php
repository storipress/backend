<?php

namespace App\Mail;

use Illuminate\Support\Carbon;

class UserPasswordResetMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $name,
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
        return 27648921;
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
            'name' => $this->name,
            'action_url' => $this->actionUrl(
                path: '/auth/password/create',
                queries: [
                    'email' => $this->email,
                    'token' => $this->token,
                    'expire_on' => (string) $this->expire_on->timestamp,
                ],
            ),
        ];
    }
}
