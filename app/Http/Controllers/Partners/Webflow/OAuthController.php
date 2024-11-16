<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners\Webflow;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Partners\PartnerController;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use App\Models\User as CentralUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User;
use Storipress\SocialiteProviders\Webflow\WebflowProvider;

class OAuthController extends PartnerController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->oauthFailed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $code = $request->get('code');

        if (!is_not_empty_string($code)) {
            return $this->oauthFailed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        // avoid code be used twice
        if (!Cache::add(sprintf('webflow_code_%s', $code), true, 60)) {
            return $this->oauthFailed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $socialite = Socialite::driver('webflow');

        if (!($socialite instanceof WebflowProvider)) {
            return $this->oauthFailed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $user = $socialite
            ->redirectUrl(secure_url(route('oauth.webflow', [], false)))
            ->stateless()
            ->user();

        if (!($user instanceof User)) {
            return $this->oauthFailed(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $authenticatable = auth()->user();

        if (!($authenticatable instanceof CentralUser)) {
            return $this->missingTenantId($user);
        }

        $tenantId = Arr::get(
            $authenticatable->access_token->data ?: [],
            'integration.webflow.key',
        );

        if (!is_not_empty_string($tenantId)) {
            return $this->oauthFailed(ErrorCode::OAUTH_INTERNAL_ERROR);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant instanceof Tenant) {
            return $this->oauthFailed(ErrorCode::OAUTH_MISSING_CLIENT);
        }

        $tenant->run(fn () => UserActivity::log(
            name: 'integration.connect',
            data: [
                'key' => 'webflow',
            ],
        ));

        OAuthConnected::dispatch($tenant->id, $user);

        return redirect()->away($this->oauthResultUrl(['ok' => '1'], false));
    }

    protected function missingTenantId(User $user): RedirectResponse
    {
        $code = Str::lower(Str::random());

        Cache::put(
            sprintf('webflow-oauth-%s', $code),
            $user,
            now()->addHour(),
        );

        $url = $this->oauthResultUrl([
            'to' => 'choose-publication',
            'code' => $code,
            'email' => $user->email,
            'client_id' => 'null',
            'integration' => 'webflow',
        ]);

        return redirect()->away($url);
    }
}
