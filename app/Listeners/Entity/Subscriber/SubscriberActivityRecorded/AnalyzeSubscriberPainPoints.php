<?php

namespace App\Listeners\Entity\Subscriber\SubscriberActivityRecorded;

use App\Events\Entity\Subscriber\SubscriberActivityRecorded;
use App\Jobs\Entity\Subscriber\AnalyzeSubscriberPainPoints as AnalyzeSubscriberPainPointsJob;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AnalyzeSubscriberPainPoints implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var string[]
     */
    public array $list = [
        'article.seen',
        'article.link.clicked',
    ];

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(SubscriberActivityRecorded $event): bool
    {
        return in_array($event->name, $this->list);
    }

    /**
     * Handle the event.
     */
    public function handle(SubscriberActivityRecorded $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        AnalyzeSubscriberPainPointsJob::dispatch($event->tenantId, $event->subscriberId);
    }
}
