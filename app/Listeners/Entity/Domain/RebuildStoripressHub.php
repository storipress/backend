<?php

namespace App\Listeners\Entity\Domain;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RebuildStoripressHub implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(): void
    {
        app('http2')->post(
            'https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/cf0506cc-0b75-4be3-9376-2c22ac608514',
        );
    }
}
