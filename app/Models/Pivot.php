<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot as BasePivot;
use Webmozart\Assert\Assert;

abstract class Pivot extends BasePivot
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool
     */
    protected $guarded = false;

    /**
     * @param  array<mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $connection = config('tenancy.database.central_connection');

        Assert::string($connection);

        $this->setConnection($connection);
    }
}
