<?php

namespace App\Mail;

class UserAppSumoRefundMail extends Mailable
{
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
        return 31153887;
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
            'name' => 'AppSumo',
        ];
    }
}
