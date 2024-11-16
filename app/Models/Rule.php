<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\Rule
 *
 * @property int $id
 * @property string $type
 * @property int $timer
 * @property int $threshold
 * @property int $frequency
 * @property int $multi_check
 * @property bool $exclusive
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $last_ran_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Action> $actions
 *
 * @method static \Database\Factories\RuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Rule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Rule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Rule query()
 *
 * @mixin \Eloquent
 */
class Rule extends Entity
{
    use HasFactory;

    protected $casts = [
        'exclusive' => 'bool',
        'activated_at' => 'datetime',
        'last_ran_at' => 'datetime',
    ];

    /**
     * @return BelongsToMany<Action>
     */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'rule_action')
            ->withPivot('id')
            ->withTimestamps();
    }
}
