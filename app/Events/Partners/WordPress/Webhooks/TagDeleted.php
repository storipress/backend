<?php

namespace App\Events\Partners\WordPress\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

class TagDeleted
{
    use Dispatchable;

    public int $wordpressId;

    /**
     * Create a new event instance.
     *
     * @param array{
     *     term_id: int,
     * } $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {
        $this->wordpressId = $this->payload['term_id'];
    }
}
