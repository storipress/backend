<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiAnalysis extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'json',
    ];

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, AiAnalysis>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
