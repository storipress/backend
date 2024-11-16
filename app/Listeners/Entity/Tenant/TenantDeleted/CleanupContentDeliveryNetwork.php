<?php

namespace App\Listeners\Entity\Tenant\TenantDeleted;

use App\Events\Entity\Tenant\TenantDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class CleanupContentDeliveryNetwork implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TenantDeleted $event): void
    {
        $redis = RedisFacade::connection('cdn')->client();

        Assert::isInstanceOf($redis, Redis::class);

        $message = json_encode([
            'event' => 'terminate',
            'tenant' => $event->tenantId,
        ]);

        Assert::stringNotEmpty($message);

        try {
            $key = sprintf('cdn_meta_%s', $event->tenantId);

            $redis->del($key);

            $channel = sprintf('cdn_caddy_%s', app()->environment());

            $redis->publish($channel, $message);
        } catch (Throwable $e) {
            captureException($e);
        }
    }
}
