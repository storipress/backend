<?php

namespace App\Http\Controllers;

use App\Console\Schedules\Weekly\RefreshTwitterProfile;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Storipress\SocialiteProviders\Twitter\TwitterProvider;
use Webmozart\Assert\Assert;

class TwitterController extends Controller
{
    /**
     * @var array<int, string>
     */
    protected array $scopes = [
        'users.read',
        'tweet.read',
        'tweet.write',
        'offline.access',
    ];

    protected TwitterProvider $client;

    public function __construct()
    {
        $client = Socialite::driver('twitter-storipress');

        Assert::isInstanceOf($client, TwitterProvider::class);

        $this->client = $client
            ->redirectUrl(Str::finish(secure_url('/twitter/oauth'), '/'))
            ->setScopes($this->scopes)
            ->stateless();
    }

    /**
     * Handle OAuth connect.
     */
    public function connect(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if (! ($user instanceof User)) {
            return $this->error();
        }

        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if (! ($manipulator instanceof TenantUser) || ! in_array($manipulator->role, ['owner', 'admin'])) {
            return $this->error();
        }

        $request->session()->put('integration.twitter.key', tenant_or_fail()->id);

        return $this->client->with(['state' => $user->access_token->token])->redirect();
    }

    /**
     * Handle OAuth callback.
     */
    public function oauth(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->input('error')) {
            return $this->error();
        }

        // avoid code used twice.
        $code = $request->input('code');

        if (! is_not_empty_string($code)) {
            return $this->error();
        }

        $lock = sprintf('twitter_code_%s', $code);

        if (! Cache::add($lock, true, 60)) {
            return $this->error();
        }

        try {
            $user = auth()->user();
        } catch (ClientException) {
            return $this->error();
        }

        if (! ($user instanceof User)) {
            return $this->error();
        }

        $tenantId = $request->session()->pull('integration.twitter.key');

        if (! is_not_empty_string($tenantId)) {
            return $this->error();
        }

        $tenant = Tenant::find($tenantId);

        if (! ($tenant instanceof Tenant)) {
            return $this->error();
        }

        $user = $this->client->user();

        if (! ($user instanceof SocialiteUser)) {
            return $this->error();
        }

        $tenant->update([
            'twitter_data' => [
                'user_id' => $user->getId(),
                'expires_on' => $user->expiresIn,
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken,
            ],
        ]);

        Artisan::call(RefreshTwitterProfile::class, [
            '--tenants' => [$tenant->id],
        ]);

        $tenant->run(function () {
            UserActivity::log(
                name: 'integration.connect',
                data: [
                    'key' => 'twitter',
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
     * Access denied json response
     */
    protected function error(): RedirectResponse
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
