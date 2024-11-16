<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionCreating;

use App\Enums\Webflow\CollectionType;
use App\Enums\Webflow\FieldType;
use App\Events\Partners\Webflow\CollectionCreating;

class CreateBlogCollection extends CreateCollection
{
    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(CollectionCreating $event): bool
    {
        return CollectionType::blog()->is($event->collectionType);
    }

    /**
     * {@inheritdoc}
     */
    public function fields(): array
    {
        return [
            [
                'displayName' => 'Excerpt',
                'type' => FieldType::richText,
                'key' => 'blurb',
            ],
            [
                'displayName' => 'Hero Photo',
                'type' => FieldType::image,
                'key' => 'cover.url',
            ],
            [
                'displayName' => 'Content',
                'type' => FieldType::richText,
                'key' => 'html',
            ],
            [
                'displayName' => 'Featured',
                'type' => FieldType::switch,
                'key' => 'featured',
            ],
            [
                'displayName' => 'Newsletter',
                'type' => FieldType::switch,
                'key' => 'newsletter',
            ],
            [
                'displayName' => 'Published Date',
                'type' => FieldType::dateTime,
                'key' => 'published_at',
            ],
            [
                'displayName' => 'Search Title',
                'type' => FieldType::plainText,
                'key' => 'seo.meta.title',
            ],
            [
                'displayName' => 'Search Description',
                'type' => FieldType::plainText,
                'key' => 'seo.meta.description',
            ],
            [
                'displayName' => 'Social Title',
                'type' => FieldType::plainText,
                'key' => 'seo.og.title',
            ],
            [
                'displayName' => 'Social Description',
                'type' => FieldType::plainText,
                'key' => 'seo.og.description',
            ],
        ];
    }
}
