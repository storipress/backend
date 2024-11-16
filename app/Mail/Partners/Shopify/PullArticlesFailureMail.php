<?php

namespace App\Mail\Partners\Shopify;

use App\Mail\Mailable;

class PullArticlesFailureMail extends Mailable
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
        return 31870430;
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
            'publication' => $this->publication,
        ];
    }
}
