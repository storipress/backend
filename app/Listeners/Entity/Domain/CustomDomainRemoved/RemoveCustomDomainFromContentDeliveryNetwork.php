<?php

namespace App\Listeners\Entity\Domain\CustomDomainRemoved;

use App\Events\Entity\Domain\CustomDomainRemoved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class RemoveCustomDomainFromContentDeliveryNetwork implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(CustomDomainRemoved $event): void
    {
        $redis = RedisFacade::connection('cdn')->client();

        Assert::isInstanceOf($redis, Redis::class);

        $message = json_encode([
            'event' => 'terminate',
            'tenant' => $event->tenantId,
        ]);

        Assert::stringNotEmpty($message);

        $key = sprintf('cdn_meta_%s', $event->tenantId);

        $channel = sprintf('cdn_caddy_%s', app()->environment());

        try {
            $redis->del($key);

            $redis->publish($channel, $message);
        } catch (Throwable $e) {
            captureException($e);
        }
    }
}
