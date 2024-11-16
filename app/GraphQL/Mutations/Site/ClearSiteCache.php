<?php

namespace App\GraphQL\Mutations\Site;

use App\Console\Commands\Cloudflare\Pages\ClearSiteCacheByTenant;
use Illuminate\Support\Facades\Artisan;

class ClearSiteCache
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        Artisan::queue(ClearSiteCacheByTenant::class, [
            'tenant' => tenant('id'),
        ]);

        return true;
    }
}
