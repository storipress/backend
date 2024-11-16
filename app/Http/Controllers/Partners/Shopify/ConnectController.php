<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\SDK\Shopify\Shopify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ConnectController extends ShopifyController
{
    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request, Shopify $shopify): RedirectResponse
    {
        return Redirect::away('https://apps.shopify.com/storipress');
    }
}
