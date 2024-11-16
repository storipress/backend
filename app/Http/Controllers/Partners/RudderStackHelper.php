<?php

namespace App\Http\Controllers\Partners;

use Segment\Segment;

trait RudderStackHelper
{
    protected function sendRudderStackEvent(string $event, int $userId, int $articleId): void
    {
        Segment::track([
            'userId' => $userId,
            'event' => $event,
            'properties' => [
                'tenant_uid' => tenant('id'),
                'tenant_name' => tenant('name'),
                'tenant_article_uid' => (string) $articleId,
            ],
            'context' => [
                'groupId' => tenant('id'),
            ],
        ]);
    }
}
