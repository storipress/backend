<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

abstract class Entity extends Model
{
    /**
     * Sortable field.
     *
     * @var string
     */
    protected static $sortableField = 'order';

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
