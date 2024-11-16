<?php

namespace App\Models;

use App\Enums\Email\EmailAbnormalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AbnormalEmail extends Entity
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, class-string|string>
     */
    protected $casts = [
        'type' => EmailAbnormalType::class,
    ];
}
