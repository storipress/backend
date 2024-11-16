<?php

namespace App\Models\Attributes;

trait FullName
{
    public function getFullNameAttribute(): ?string
    {
        $first = $this->getAttributeValue('first_name');

        $last = $this->getAttributeValue('last_name');

        $full = $first . ' ' . $last;

        return trim($full) ?: null;
    }
}
