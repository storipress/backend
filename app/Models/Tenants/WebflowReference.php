<?php

namespace App\Models\Tenants;

/**
 * App\Models\Tenants\WebflowReference
 *
 * @property string $id
 */
class WebflowReference extends Entity
{
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
