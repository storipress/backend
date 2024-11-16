<?php

namespace App\Jobs\Tenants;

use App\Models\Tenants\Page;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateDefaultPages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->tenant->run(function () {
            $pages = ['about-us', 'privacy-policy'];

            foreach ($pages as $page) {
                $path = resource_path(sprintf('pages/%s.json', $page));

                $content = file_get_contents($path);

                if (!$content) {
                    continue;
                }

                /** @var array<string, mixed> $data */
                $data = json_decode($content, true);

                Page::create(Arr::except($data, ['order']));
            }
        });
    }
}
