<?php

namespace App\Events\Partners\Postmark;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookReceiving
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function __construct(
        public array $inputs,
        public string $body,
    ) {
        //
    }
}
