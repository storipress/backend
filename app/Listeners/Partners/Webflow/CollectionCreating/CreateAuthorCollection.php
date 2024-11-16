<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\CollectionCreating;

use App\Enums\Webflow\CollectionType;
use App\Enums\Webflow\FieldType;
use App\Events\Partners\Webflow\CollectionCreating;

class CreateAuthorCollection extends CreateCollection
{
    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(CollectionCreating $event): bool
    {
        return CollectionType::author()->is($event->collectionType);
    }

    /**
     * {@inheritdoc}
     */
    public function fields(): array
    {
        return [
            [
                'displayName' => 'Contact Email',
                'type' => FieldType::email,
                'key' => 'contact_email',
            ],
            [
                'displayName' => 'Job Title',
                'type' => FieldType::plainText,
                'key' => 'job_title',
            ],
            [
                'displayName' => 'Website',
                'type' => FieldType::link,
                'key' => 'website',
            ],
            [
                'displayName' => 'Location',
                'type' => FieldType::plainText,
                'key' => 'location',
            ],
            [
                'displayName' => 'Bio',
                'type' => FieldType::plainText,
                'key' => 'bio',
            ],
            [
                'displayName' => 'Photo',
                'type' => FieldType::image,
                'key' => 'avatar',
            ],
            [
                'displayName' => 'Twitter',
                'type' => FieldType::link,
                'key' => 'social.twitter',
            ],
            [
                'displayName' => 'Facebook',
                'type' => FieldType::link,
                'key' => 'social.facebook',
            ],
            [
                'displayName' => 'Instagram',
                'type' => FieldType::link,
                'key' => 'social.instagram',
            ],
            [
                'displayName' => 'LinkedIn',
                'type' => FieldType::link,
                'key' => 'social.linkedin',
            ],
            [
                'displayName' => 'YouTube',
                'type' => FieldType::link,
                'key' => 'social.youtube',
            ],
            [
                'displayName' => 'Pinterest',
                'type' => FieldType::link,
                'key' => 'social.pinterest',
            ],
            [
                'displayName' => 'WhatsApp',
                'type' => FieldType::link,
                'key' => 'social.whatsapp',
            ],
            [
                'displayName' => 'Reddit',
                'type' => FieldType::link,
                'key' => 'social.reddit',
            ],
            [
                'displayName' => 'TikTok',
                'type' => FieldType::link,
                'key' => 'social.tiktok',
            ],
            [
                'displayName' => 'Geneva',
                'type' => FieldType::link,
                'key' => 'social.geneva',
            ],
        ];
    }
}
