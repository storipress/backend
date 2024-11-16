<?php

namespace App\Events\Partners\WordPress\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

class PostDeleted
{
    use Dispatchable;

    public int $wordpressId;

    /**
     * Create a new event instance.
     *
     * @param array{
     *     post_id: int,
     * } $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {
        $this->wordpressId = $this->payload['post_id'];
    }
}
