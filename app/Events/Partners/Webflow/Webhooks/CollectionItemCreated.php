<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

class CollectionItemCreated
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
     *     lastPublished: string|null,
     *     createOn: string,
     *     isArchived: bool,
     *     isDraft: bool,
     *     fieldData: non-empty-array<non-empty-string, mixed>
     * }  $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {
        //
    }
}
