<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

abstract class Entity extends Model
{
    /**
     * Sortable field.
     *
     * @var string
     */
    protected static $sortableField = 'order';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool
     */
    protected $guarded = false;
}
