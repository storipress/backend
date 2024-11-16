<?php

namespace App\Events\Entity\CustomField;

use Illuminate\Foundation\Events\Dispatchable;

class CustomFieldValueUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $valueId,
    ) {
        //
    }
}
