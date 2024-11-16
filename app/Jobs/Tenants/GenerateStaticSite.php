<?php

namespace App\Jobs\Tenants;

use App\Builder\ReleaseEventsBuilder;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Segment\Segment;

final class GenerateStaticSite implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
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
        $builder = new ReleaseEventsBuilder();

        $this->tenant->run(function () use ($builder) {
            $builder->handle('site:generate');
        });

        $this->tenant->update(['initialized' => true]);

        Segment::track([
            'userId' => (string) $this->tenant->owner->id,
            'event' => 'tenant_created',
            'properties' => [
                'tenant_uid' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
            ],
            'context' => [
                'groupId' => $this->tenant->id,
            ],
        ]);

        Segment::track([
            'userId' => (string) $this->tenant->owner->id,
            'event' => 'tenant_joined',
            'properties' => [
                'tenant_uid' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
                'user_role' => 'owner',
                'invited' => false,
            ],
            'context' => [
                'groupId' => $this->tenant->id,
            ],
        ]);
    }
}
