<?php

namespace App\Jobs\Scraper;

use App\Mail\UserScraperResultMail;
use App\Models\Tenant;
use App\Models\Tenants\Scraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Segment\Segment;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

class SendScraperResultEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        protected int $scraperId,
        protected string $token,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            tenancy()->initialize($this->tenantId);
        } catch (TenantCouldNotBeIdentifiedById) {
            return;
        }

        $scraper = Scraper::find($this->scraperId);

        if ($scraper === null) {
            return;
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        $total = $scraper->articles()
            ->whereNotNull('article_id')
            ->where('successful', true)
            ->count();

        Mail::to($tenant->owner->email)->send(
            new UserScraperResultMail(
                token: $this->token,
                articlesCount: $total,
            ),
        );

        Segment::track([
            'userId' => (string) $tenant->owner->id,
            'event' => 'tenant_scrape_succeed',
            'properties' => [
                'tenant_uid' => $tenant->id,
                'tenant_name' => $tenant->name,
                'imported_articles' => $total,
            ],
            'context' => [
                'groupId' => $tenant->id,
            ],
        ]);
    }
}
