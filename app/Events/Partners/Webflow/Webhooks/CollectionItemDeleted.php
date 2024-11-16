<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

class CollectionItemDeleted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array{
     *     id: string,
     *     siteId: string,
     *     workspaceId: string,
     *     collectionId: string,
     * }  $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {
        //
    }
}
