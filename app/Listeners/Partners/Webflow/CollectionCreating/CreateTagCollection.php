<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionCreating;

use App\Enums\Webflow\CollectionType;
use App\Enums\Webflow\FieldType;
use App\Events\Partners\Webflow\CollectionCreating;

class CreateTagCollection extends CreateCollection
{
    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(CollectionCreating $event): bool
    {
        return CollectionType::tag()->is($event->collectionType);
    }

    /**
     * {@inheritdoc}
     */
    public function fields(): array
    {
        return [
            [
                'displayName' => 'Description',
                'type' => FieldType::plainText,
                'key' => 'description',
            ],
        ];
    }
}
