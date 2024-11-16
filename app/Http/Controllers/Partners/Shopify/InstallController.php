<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\Exceptions\ErrorCode;
use App\SDK\Shopify\Shopify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstallController extends ShopifyController
{
    /**
     * Handle the incoming request.
     *
     * @return RedirectResponse|JsonResponse
     */
    public function __invoke(Request $request)
    {
        $verified = $this->verifyRequest($request);

        if (!$verified) {
            return $this->failed(ErrorCode::OAUTH_INVALID_PAYLOAD);
        }

        return (new Shopify())->redirect('from-install');
    }
}
