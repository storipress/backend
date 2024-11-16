<?php

namespace App\Notifications\Traits;

use Illuminate\Notifications\Messages\MailMessage;

trait HasMailChannel
{
    protected function mail(): MailMessage
    {
        return (new MailMessage())
            ->mailer('postmark-notification')
            ->replyTo('support@storipress.com');
    }
}
