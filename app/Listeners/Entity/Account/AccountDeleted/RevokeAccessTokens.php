<?php

namespace App\Listeners\Entity\Account\AccountDeleted;

use App\Events\Entity\Account\AccountDeleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RevokeAccessTokens implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(AccountDeleted $event): void
    {
        $user = User::find($event->userId);

        if (! ($user instanceof User)) {
            return;
        }

        $now = now();

        $user
            ->accessTokens()
            ->where('expires_at', '>', $now)
            ->update(['expires_at' => $now]);
    }
}
