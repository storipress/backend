<?php

namespace App\Notifications\Migration;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;

abstract class WordPressNotification extends Notification
{
    use HasMailChannel;
}
