<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Relations\Pivot as BasePivot;

abstract class Pivot extends BasePivot
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool
     */
    protected $guarded = false;
}
