<?php

namespace App\Listeners\Partners\Zapier\WebhookPush;

use App\Events\WebhookPushing;
use App\Listeners\Partners\Zapier\ZapierWebhookDelivery;
use App\Models\Tenants\Article;

class PushArticleDeleted extends ZapierWebhookDelivery
{
    /**
     * @var string
     */
    protected $topic = 'article.deleted';

    /**
     * Determine whether the listener should be queued.
     *
     * @param  WebhookPushing<Article>  $event
     */
    public function shouldQueue(WebhookPushing $event): bool
    {
        return $event->topic === $this->topic
            && $event->model instanceof Article;
    }
}
