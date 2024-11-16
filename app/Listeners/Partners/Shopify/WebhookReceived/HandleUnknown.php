<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Events\Partners\Shopify\WebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleUnknown implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->topic === 'unknown';
    }

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        Log::debug('Unhandled Shopify Webhook Received', [
            'topic' => $event->topic,
            'payload' => $event->payload,
        ]);
    }
}
