<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\Tenants\Analysis
 *
 * @property int $id
 * @property int $subscribers
 * @property int $paid_subscribers
 * @property int $active_subscribers
 * @property int $revenue
 * @property int $email_sends
 * @property int $email_opens
 * @property int $email_clicks
 * @property int|null $year
 * @property int|null $month
 * @property \Illuminate\Support\Carbon|null $date
 *
 * @method static \Database\Factories\Tenants\AnalysisFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Analysis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Analysis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Analysis query()
 *
 * @mixin \Eloquent
 */
class Analysis extends Entity
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscriber_analyses';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime:Y-m-d',
    ];
}
