<?php

namespace App\Models\Attributes;

use App\Models\Media;
use App\Models\User;

trait Avatar
{
    public function getAvatarAttribute(): string
    {
        if ($this instanceof User && $this->id === 1) {
            return 'https://assets.stori.press/storipress/storipress-helper-user-avatar.webp';
        }

        /** @var Media|null $media */
        $media = $this->getRelationValue('avatar');

        if ($media !== null) {
            return $media->url;
        }

        return sprintf(
            'https://api.dicebear.com/7.x/initials/png?seed=%s&size=256',
            rawurlencode($this->full_name ?: 'default'),
        );
    }
}
