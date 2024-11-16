<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('n.{hash}', function ($user, $id) {
    if (!($user instanceof User)) {
        return false;
    }

    if (!is_string($id) || strlen($id) !== 64) {
        return false;
    }

    return $user->intercom_hash_identity === $id;
});
