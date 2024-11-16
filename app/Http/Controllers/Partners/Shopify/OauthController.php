<?php

namespace App\Http\Controllers\Partners\Shopify;

use App\Events\Partners\Shopify\OAuthConnected;
use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use App\Resources\Partners\Shopify\Shop;
use App\SDK\Shopify\Shopify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OauthController extends ShopifyController
{
    public function __invoke(Request $request, Shopify $shopify): JsonResponse|RedirectResponse
    {
        if (!$this->verifyRequest($request)) {
            return $this->failed(ErrorCode::OAUTH_INVALID_PAYLOAD);
        }

        // avoid code used twice.
        $code = $request->get('code');

        if (Cache::has('shopify_code_' . $code)) {
            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        Cache::put('shopify_code_' . $code, true, 60);

        $payload = $shopify->user();

        $shop = new Shop(
            id: $payload->user['id'],
            name: $payload->user['name'],
            email: $payload->user['email'],
            domain: $payload->user['domain'],
            myshopifyDomain: $payload->user['myshopify_domain'],
        );

        $user = auth()->user();

        if ($user instanceof User) {
            $data = $user->access_token->data ?: [];

            $key = Arr::get($data, 'integration.shopify.key');

            if (!is_string($key) || empty($key)) {
                return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
            }
        } else {
            $key = null;
        }

        $code = Str::lower(Str::random());

        $referrer = Arr::get($data ?? [], 'integration.shopify.referrer');

        $scopes = array_values(array_filter(explode(',', $payload->accessTokenResponseBody['scope'])));

        if ($key === null) {
            Cache::put(
                'shopify-oauth-' . $code,
                [
                    'token' => $payload->token,
                    'scopes' => $scopes,
                    'shop' => $shop,
                ],
                now()->addHour(),
            );
        } else {
            $tenant = Tenant::where('id', $key)->first();

            if (empty($tenant)) {
                return $this->failed(ErrorCode::OAUTH_INTERNAL_ERROR);
            }

            $tenant->run(fn () => UserActivity::log(
                name: 'integration.connect',
                data: [
                    'key' => 'shopify',
                ],
            ));

            OAuthConnected::dispatch(
                $payload->token,
                $scopes,
                $shop,
                $key,
            );
        }

        return redirect()->away($this->oauthResultUrl([
            'to' => $key === null
                ? 'choose-publication'
                : ($referrer === 'migration' ? 'migration' : 'integration'),
            'code' => $code,
            'email' => $shop->email,
            'client_id' => $key ?: 'null',
            'integration' => 'shopify',
        ]));
    }

    protected function findTenant(string $email): mixed
    {
        return User::whereEmail($email)
            ->first()
            ?->publications()
            ->whereJsonDoesntContainKey('data->shopify_data->id')
            ->value('tenants.id');
    }
}
