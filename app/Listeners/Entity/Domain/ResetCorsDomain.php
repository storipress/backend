<?php

namespace App\Listeners\Entity\Domain;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class ResetCorsDomain implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(): void
    {
        $key = config('cache-keys.cors.domains');

        if (! is_not_empty_string($key)) {
            return;
        }

        tenancy()->central(fn () => Cache::forget($key));
    }
}
