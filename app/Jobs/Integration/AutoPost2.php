<?php

namespace App\Jobs\Integration;

use App\AutoPosting\Dispatcher;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Webmozart\Assert\Assert;

class AutoPost2 implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  'create'|'update'|'unpublish'|'trash'  $action
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
        public string $action,
    ) {
    }

    /**
     * Execute the job.
     *
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function handle(): void
    {
        $tenant = Tenant::where('id', '=', $this->tenantId)->sole();

        if (app()->environment('development') && $tenant->user_id === 17) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $article = Article::withTrashed()->with('autoPostings')->find($this->articleId);

            Assert::isInstanceOf($article, Article::class);

            $pipe = new Dispatcher($tenant, $article, $this->action, []);

            $pipe->handle();
        });
    }
}
