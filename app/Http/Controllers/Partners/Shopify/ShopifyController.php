<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\Http\Controllers\Partners\PartnerController;
use Illuminate\Http\Request;

abstract class ShopifyController extends PartnerController
{
    public function verifyRequest(Request $request): bool
    {
        $secret = config('services.shopify.client_secret', '');

        if (! is_string($secret) || empty($secret)) {
            return false;
        }

        $hmac = $request->input('hmac');

        if (! is_string($hmac) || strlen($hmac) !== 64) {
            return false;
        }

        $params = $request->except('hmac');

        ksort($params);

        $known = hash_hmac(
            'sha256',
            http_build_query($params),
            $secret,
        );

        return hash_equals($known, $hmac);
    }
}
