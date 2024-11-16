<?php

namespace App\SDK\Slack;

use App\SDK\SocialPlatformsInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User;
use SocialiteProviders\Slack\Provider2 as SlackProvider;
use Webmozart\Assert\Assert;

class Slack implements SocialPlatformsInterface
{
    public const BASE_URL = 'https://slack.com/api/';

    /**
     * @var string[]
     */
    protected $userScopes = [
        'identity.basic',
        'identity.email',
        'identity.team',
        'identity.avatar',
    ];

    /**
     * @var string[]
     */
    protected $scopes = [
        'users:read',
        'users:read.email',
        'channels:read',
        'groups:read',
        'chat:write',
        'chat:write.public',
        'im:write',
        'app_mentions:read',
    ];

    /**
     * @var string[];
     */
    protected $authErrorMessages = [
        'not_authed',
        'invalid_auth',
        'access_denied',
        'account_inactive',
        'token_revoked',

    ];

    /**
     * @var SlackProvider
     */
    public $client;

    /**
     * @var PendingRequest
     */
    protected $http;

    public function __construct()
    {
        $client = Socialite::driver('slack2');

        Assert::isInstanceOf($client, SlackProvider::class);

        $this->client = $client;

        $this->client
            ->redirectUrl(Str::finish(secure_url('/slack/oauth'), '/'))
            ->setScopes($this->scopes)
            ->setUserScopes($this->userScopes)
            ->stateless();

        $this->http = Http::baseUrl(self::BASE_URL)
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(3);
    }

    public function redirect(string $token): RedirectResponse
    {
        return $this->client->with(['state' => $token])->redirect();
    }

    public function user(): User
    {
        $user = $this->client->user();

        Assert::isInstanceOf($user, User::class);

        return $user;
    }

    /**
     * get the team channels list
     *
     * @return array<array{id:string, name:string, is_private: bool}>
     */
    public function getChannelsList(string $token): array
    {
        $response = $this->request('get', 'conversations.list', [
            'token' => $token,
            'types' => 'public_channel,private_channel',
            'exclude_archived' => true,
            'limit' => 1000,
        ]);

        /** @var array{array{id:string, name:string, is_channel:bool, is_private: bool}} $channels */
        $channels = $response->json('channels');

        $result = [];

        foreach ($channels as $channel) {
            if ($channel['is_channel'] === false) {
                continue;
            }

            /** @var array{id:string, name:string, is_private: bool} $data */
            $data = Arr::only($channel, ['id', 'name', 'is_private']);

            $result[] = $data;
        }

        return $result;
    }

    /**
     * @return array<array{id:string, name:string, email: string}>
     */
    public function getUsersList(string $token): array
    {
        $response = $this->request('get', 'users.list', [
            'token' => $token,
        ]);

        /** @var array{array{id:string, name:string, profile:array{email:string}, deleted: bool, is_email_confirmed:bool, is_bot:bool}} $members */
        $members = $response->json('members');

        $result = [];

        foreach ($members as $member) {
            if ($member['deleted'] === true) {
                continue;
            }

            if ($member['is_email_confirmed'] === false) {
                continue;
            }

            if ($member['is_bot'] === true) {
                continue;
            }

            $result[] = [
                'id' => $member['id'],
                'name' => $member['name'],
                'email' => $member['profile']['email'],
            ];
        }

        return $result;
    }

    public function sendDirectMessage(): void
    {
        // create a channel
        // post message to channel
    }

    public function postMessage(string $token, string $channel, string $blocks): Response
    {
        return $this->request('get', 'chat.postMessage', [
            'token' => $token,
            'channel' => $channel,
            'blocks' => $blocks,
        ]);
    }

    public function revoke(string $token): void
    {
        $this->request('get', 'auth.revoke', [
            'token' => $token,
        ]);
    }

    public function uninstall(string $token): void
    {
        /** @var string $clientId */
        $clientId = config('services.slack2.client_id', '');

        /** @var string $clientSecret */
        $clientSecret = config('services.slack2.client_secret', '');

        $this->request('get', 'apps.uninstall', [
            'token' => $token,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
    }

    public function parseBotAccessToken(User $user): ?string
    {
        $body = $user->accessTokenResponseBody;

        /** @var string|null $token */
        $token = Arr::get($body, 'access_token');

        return $token;
    }

    /**
     * @return array{id:string, name:string, domain: string, avatar: string}
     */
    public function parseTeamInfo(User $user): array
    {
        $raw = $user->getRaw();

        /** @var string $id */
        $id = Arr::get($raw, 'team.id', '');

        /** @var string $name */
        $name = Arr::get($raw, 'team.name', '');

        /** @var string $domain */
        $domain = Arr::get($raw, 'team.domain', '');

        /** @var string $avatar */
        $avatar = Arr::get($raw, 'team.image_230', '');

        return [
            'id' => $id,
            'name' => $name,
            'domain' => $domain,
            'avatar' => $avatar,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function request(string $method, string $path, array $params = []): Response
    {
        /** @var string $token */
        $token = Arr::pull($params, 'token', '');

        $response = $this->http->withToken($token)->{$method}($path, $params);

        $ok = $response->json('ok');

        if ($ok === false) {
            $message = $response->json('error');
            if (in_array($message, $this->authErrorMessages)) {
                throw new \RuntimeException('Invalid credentials.', 401);
            }
            // the others ignore.
            Log::debug('[Slack Error] ' . $path, [
                'params' => $params,
                'response' => $response->json(),
            ]);
        }

        return $response;
    }
}
