<?php

namespace App\Packages\Postmark;

use CraigPaul\Mail\PostmarkTransport as BaseTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

class PostmarkTransport extends BaseTransport
{
    /**
     * @return array<string, string|string[]>
     */
    protected function getPayload(Email $email, Envelope $envelope): array
    {
        $streamId = config('mail.mailers.postmark.message_stream_id');

        if (is_string($streamId)) {
            $this->messageStreamId = $streamId;
        }

        return parent::getPayload($email, $envelope);
    }
}
