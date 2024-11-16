<?php

namespace App\Events;

use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * @template TModel of Article|Subscriber
 */
class WebhookPushing
{
    use Dispatchable;

    /**
     * @param  TModel  $model
     */
    public function __construct(
        public string $tenantId,
        public string $topic,
        public $model,
    ) {
        //
    }
}
