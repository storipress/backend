<?php

namespace App\Listeners\Partners\Shopify\OAuthConnected;

use App\Events\Partners\Shopify\OAuthConnected;
use App\Listeners\Traits\ShopifyTrait;
use App\SDK\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class InjectThemeTemplate implements ShouldQueue
{
    use InteractsWithQueue;
    use ShopifyTrait;

    public function __construct(protected Shopify $app)
    {

    }

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $this->app->setShop($event->shop->myshopifyDomain);

        $this->app->setAccessToken($event->token);

        $this->injectThemeTemplate($this->app, $event->tenantId, $event->shop->myshopifyDomain);
    }
}
