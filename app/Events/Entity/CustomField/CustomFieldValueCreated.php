<?php

namespace App\Events\Entity\CustomField;

use Illuminate\Foundation\Events\Dispatchable;

class CustomFieldValueCreated
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
