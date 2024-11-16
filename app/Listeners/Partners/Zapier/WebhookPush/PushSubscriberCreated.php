<?php

namespace App\Listeners\Partners\Zapier\WebhookPush;

use App\Events\WebhookPushing;
use App\Listeners\Partners\Zapier\ZapierWebhookDelivery;
use App\Models\Tenants\Subscriber;

class PushSubscriberCreated extends ZapierWebhookDelivery
{
    /**
     * @var string
     */
    protected $topic = 'subscriber.created';

    /**
     * Determine whether the listener should be queued.
     *
     * @param  WebhookPushing<Subscriber>  $event
     */
    public function shouldQueue(WebhookPushing $event): bool
    {
        return $event->topic === $this->topic
            && $event->model instanceof Subscriber;
    }
}
