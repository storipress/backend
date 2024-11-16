<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Redirection
 *
 * @property int $id
 * @property string $path
 * @property string $target
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection query()
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Redirection withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Redirection extends Entity
{
    use SoftDeletes;
}
