<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\SDK\Shopify\Shopify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ConnectReauthorizeController extends ShopifyController
{
    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request, Shopify $shopify): RedirectResponse|JsonResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return $this->failed(ErrorCode::OAUTH_UNAUTHORIZED_REQUEST);
        }

        if (!($user instanceof User)) {
            return $this->failed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $manipulator = TenantUser::find($user->id, ['id', 'role']);

        if (!in_array($manipulator?->role, ['owner', 'admin'], true)) {
            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $key = $tenant->id;

        $domain = Arr::get($tenant->shopify_data ?: [], 'myshopify_domain');

        if (!is_not_empty_string($domain)) {
            return $this->failed(ErrorCode::SHOPIFY_INTEGRATION_NOT_CONNECT);
        }

        $data = $user->access_token->data ?: [];

        Arr::set($data, 'integration.shopify.key', $key);

        Arr::set($data, 'integration.shopify.referrer', $request->input('referrer'));

        $user->access_token->update(['data' => $data]);

        return $shopify->redirect($user->access_token->token, $domain);
    }
}
