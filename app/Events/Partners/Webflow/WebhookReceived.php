<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookReceived
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  non-empty-array<non-empty-string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $topic,
        public array $payload,
    ) {
        //
    }
}
