<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Tenants\Linter
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $prompt
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Linter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Linter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Linter onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Linter query()
 * @method static \Illuminate\Database\Eloquent\Builder|Linter withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Linter withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Linter extends Entity
{
    use SoftDeletes;
}
