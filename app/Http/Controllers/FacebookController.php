<?php

namespace App\Http\Controllers;

use App\Console\Schedules\Weekly\RefreshFacebookProfile;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Storipress\Facebook\Requests\Request as FacebookSDK;
use Webmozart\Assert\Assert;

class FacebookController extends Controller
{
    /**
     * @var array<int, string>
     */
    protected array $scopes = [
        'pages_manage_posts',
    ];

    protected FacebookProvider $client;

    public function __construct()
    {
        $client = Socialite::driver('facebook');

        Assert::isInstanceOf($client, FacebookProvider::class);

        $this->client = $client
            ->redirectUrl(Str::finish(secure_url('/facebook/oauth'), '/'))
            ->usingGraphVersion(FacebookSDK::VERSION)
            ->setScopes($this->scopes)
            ->stateless();
    }

    /**
     * Handle OAuth connect.
     */
    public function connect(): RedirectResponse
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            return $this->error();
        }

        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if (! ($manipulator instanceof TenantUser) || ! in_array($manipulator->role, ['owner', 'admin'])) {
            return $this->error();
        }

        $data = $user->access_token->data ?: [];

        data_set($data, 'integration.facebook.key', tenant_or_fail()->id);

        $user->access_token->update(['data' => $data]);

        return $this->client->with(['state' => $user->access_token->token])->asPopup()->redirect();
    }

    /**
     * Handle OAuth callback.
     */
    public function oauth(Request $request): RedirectResponse
    {
        if ($request->input('error')) {
            return $this->error();
        }

        // avoid code used twice.
        $code = $request->input('code');

        if (! is_not_empty_string($code)) {
            return $this->error();
        }

        $lock = sprintf('facebook_code_%s', $code);

        if (! Cache::add($lock, true, 60)) {
            return $this->error();
        }

        $user = auth()->user();

        if (! ($user instanceof User)) {
            return $this->error();
        }

        $tenantId = data_get($user->access_token->data, 'integration.facebook.key');

        if (! is_not_empty_string($tenantId)) {
            return $this->error();
        }

        $tenant = Tenant::find($tenantId);

        if (! ($tenant instanceof Tenant)) {
            return $this->error();
        }

        $user = $this->client->fields(['id', 'permissions'])->user();

        if (! ($user instanceof SocialiteUser)) {
            return $this->error();
        }

        $permissions = $user->offsetGet('permissions');

        if (! is_array($permissions) || ! is_iterable($permissions['data'])) {
            return $this->error();
        }

        foreach ($permissions['data'] as $permission) {
            if ($permission['status'] !== 'granted') {
                return $this->error();
            }
        }

        $tenant->update([
            'facebook_data' => [
                'user_id' => $user->getId(),
                'access_token' => $user->token,
            ],
        ]);

        Artisan::call(RefreshFacebookProfile::class, [
            '--tenants' => [$tenant->id],
        ]);

        $tenant->run(function () {
            UserActivity::log(
                name: 'integration.connect',
                data: [
                    'key' => 'facebook',
                ],
            );
        });

        return redirect()->away(
            $this->url([
                'response' => json_encode([]) ?: '',
            ]),
        );
    }

    /**
     * Handle revoke data callback from Facebook.
     */
    public function revoke(Request $request): void
    {
        $signed = $request->input('signed_request');

        if (! is_not_empty_string($signed)) {
            return;
        }

        $id = $this->parseSignedRequest($signed);

        if ($id === null) {
            return;
        }

        $tenants = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->whereJsonContains('data->facebook_data->user_id', $id)
            ->lazyById(50);

        runForTenants(function (Tenant $tenant) {
            Integration::find('facebook')?->reset();

            $tenant->update(['facebook_data' => null]);
        }, $tenants);
    }

    /**
     * @link https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
     */
    public function parseSignedRequest(string $signedRequest): ?string
    {
        $secret = config('services.facebook.client_secret');

        if (! is_not_empty_string($secret)) {
            return null;
        }

        [$encoded, $payload] = explode('.', $signedRequest, 2);

        $data = json_decode($this->base64UrlDecode($payload), true);

        // confirm the signature
        $expected = hash_hmac('sha256', $payload, $secret, true);

        // decode the data
        $signed = $this->base64UrlDecode($encoded);

        if (! hash_equals($signed, $expected)) {
            return null;
        }

        if (! is_array($data) || ! isset($data['user_id'])) {
            return null;
        }

        return $data['user_id'];
    }

    public function base64UrlDecode(string $input): string
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Access denied json response
     */
    public function error(): RedirectResponse
    {
        return redirect()->away(
            $this->url([
                'response' => json_encode(['error' => 'Access Denied.']) ?: '',
            ]),
        );
    }

    /**
     * @param  array<string, string>  $queries
     */
    public function url(array $queries = []): string
    {
        return urldecode(app_url('/social-connected.html', $queries));
    }
}
