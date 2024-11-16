<?php

namespace App\Http\Controllers;

use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\User as TenantUser;
use App\Models\Tenants\UserActivity;
use App\Models\User;
use App\SDK\Slack\Slack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Webmozart\Assert\Assert;

class SlackController extends Controller
{
    /**
     * redirect to authorize url
     */
    public function connect(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return $this->accessDeniedJsonResponse();
        }

        Assert::isInstanceOf($user, User::class);

        /** @var TenantUser|null $manipulator */
        $manipulator = TenantUser::find($user->getAuthIdentifier());

        if ($manipulator === null || !in_array($manipulator->role, ['owner', 'admin'])) {
            return $this->accessDeniedJsonResponse();
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $key = $tenant->getKey();

        Assert::stringNotEmpty($key);

        $data = $user->access_token->data ?: [];

        data_set($data, 'integration.slack.key', $key);

        $user->access_token->update(['data' => $data]);

        return (new Slack())->redirect($user->access_token->token);
    }

    /**
     * oauth callback
     */
    public function oauth(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->get('error')) {
            return $this->accessDeniedJsonResponse();
        }

        $user = auth()->user();

        if ($user === null) {
            return $this->accessDeniedJsonResponse();
        }

        Assert::isInstanceOf($user, User::class);

        $key = data_get($user->access_token->data, 'integration.slack.key');

        if (!is_string($key) || empty($key)) {
            return $this->accessDeniedJsonResponse();
        }

        $tenant = Tenant::find($key);

        if ($tenant === null) {
            return $this->accessDeniedJsonResponse();
        }

        tenancy()->initialize($tenant);

        // avoid code used twice.
        $code = $request->get('code');

        if (Cache::has('slack_code_' . $code)) {
            return $this->failed(ErrorCode::OAUTH_FORBIDDEN_REQUEST);
        }

        Cache::put('slack_code_' . $code, true, 60);

        $client = new Slack();

        $user = $client->user();

        $botToken = $client->parseBotAccessToken($user);

        $userToken = $user->token;

        $team = $client->parseTeamInfo($user);

        /** @var Integration $slack */
        $slack = Integration::find('slack');

        $slack->internals = [
            'id' => $team['id'],
            'name' => $team['name'],
            'thumbnail' => $team['avatar'],
            'bot_access_token' => $botToken,
            'user_access_token' => $userToken,
        ];

        $slack->data = [
            'id' => $team['id'],
            'name' => $team['name'],
            'thumbnail' => $team['avatar'],
            'published' => [],
            'stage' => [],
            'notifyAuthors' => false,
        ];

        $slack->save();

        $tenant->slack_data = [
            'team_id' => $team['id'],
        ];

        $tenant->save();

        UserActivity::log(
            name: 'integration.connect',
            data: [
                'key' => 'slack',
            ],
        );

        return redirect()->away($this->getResponseUrl(['response' => json_encode([]) ?: '']));
    }

    /**
     * oauth callback
     */
    public function events(Request $request): JsonResponse
    {
        if (!$this->validateSignature($request)) {
            return response()->json(['error' => 'Invalid token.']);
        }

        /** @var array{type:string}|null $event */
        $event = $request->get('event');

        $type = $event['type'] ?? '';

        switch ($type) {
            case 'app_uninstalled':
                /** @var string $teamId */
                $teamId = $request->get('team_id');

                $this->clearAllTeamData($teamId);
                break;
            case 'app_mention':
                // parse event.text
                break;
            case 'user_profile_changed':
            case 'team_join':
                // update user list
                break;
            default:
                break;
        }

        return response()->json(['challenge' => $request->get('challenge')]);
    }

    /**
     * @param  array<string, string>  $queries
     */
    protected function getResponseUrl(array $queries = []): string
    {
        $host = match (app()->environment()) {
            'local' => 'http://localhost:3333',
            'development' => 'https://storipress.dev',
            'staging' => 'https://storipress.pro',
            default => 'https://stori.press',
        };

        return urldecode(sprintf(
            '%s/social-connected.html?%s',
            $host,
            http_build_query($queries),
        ));
    }

    /**
     * Access denied json response
     */
    protected function accessDeniedJsonResponse(): RedirectResponse
    {
        return redirect()->away($this->getResponseUrl([
            'response' => json_encode(['error' => 'Access Denied.']) ?: '',
        ]));
    }

    protected function clearAllTeamData(string $teamId): void
    {
        $tenants = Tenant::whereJsonContains('data->slack_data->team_id', $teamId)
            ->where('initialized', true)
            ->get();

        tenancy()->runForMultiple(
            $tenants,
            function (Tenant $tenant) {
                /** @var Integration $slack */
                $slack = Integration::find('slack');

                $slack->revoke();

                $tenant->slack_data = null;

                $tenant->save();
            },
        );
    }

    protected function validateSignature(Request $request): bool
    {
        $version = 'v0';

        $body = $request->getContent();

        $timestamp = $request->header('X-Slack-Request-Timestamp');

        if (!is_string($timestamp) || empty($timestamp)) {
            return false;
        }

        $signingBase = $version . ':' . $timestamp . ':' . $body;

        /** @var string $secret */
        $secret = config('services.slack2.signing_secret', '');

        $signature = $version . '=' . hash_hmac('sha256', $signingBase, $secret);

        $slackSignature = $request->header('X-Slack-Signature');

        if (!is_string($slackSignature) || empty($slackSignature)) {
            return false;
        }

        /**
         * The request timestamp is more than five minutes from local time.
         * It could be a replay attack.
         */
        if (now()->diffInMinutes(Carbon::createFromTimestamp($timestamp)) > 5) {
            return false;
        }

        return hash_equals($signature, $slackSignature);
    }
}
