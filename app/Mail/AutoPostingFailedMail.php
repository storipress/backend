<?php

namespace App\Mail;

class AutoPostingFailedMail extends Mailable
{
    public function __construct(public string $platform, public string $hint)
    {
        parent::__construct();
    }

    protected function server(): string
    {
        return 'app_server_token';
    }

    protected function id(): int
    {
        return 0; // TODO
    }

    protected function data(): array
    {
        return [
            'publication' => $this->publication,
            'platform' => $this->platform,
            'hint' => $this->hint,
        ];
    }
}
