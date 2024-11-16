<?php

namespace App\SDK\LinkedIn;

use App\Resources\Partners\LinkedIn\Article;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\LinkedInProvider;
use Laravel\Socialite\Two\User;
use Webmozart\Assert\Assert;

class LinkedIn
{
    public const BASE_URL = 'https://api.linkedin.com/rest/';

    public const OAUTH_URL = 'https://www.linkedin.com/oauth/v2/';

    protected const PROTOCOL_VERSION = '2.0.0';

    protected const LINKEDIN_VERSION = '202304';

    protected LinkedInProvider $client;

    /**
     * @var array<string>
     */
    protected $scopes = [
        'w_organization_social',
        'w_member_social',
        'r_organization_admin',
        'r_liteprofile',
        'r_emailaddress',
    ];

    protected PendingRequest $http;

    public function __construct()
    {
        $client = Socialite::driver('linkedin');

        Assert::isInstanceOf($client, LinkedInProvider::class);

        $this->client = $client;

        $this->client
            ->redirectUrl(route('oauth.linkedin'))
            ->setScopes($this->scopes)
            ->stateless();

        $this->http = app('http')
            ->baseUrl(self::BASE_URL)
            ->withHeaders([
                'X-Restli-Protocol-Version' => self::PROTOCOL_VERSION,
                'LinkedIn-Version' => self::LINKEDIN_VERSION,
            ]);
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

    public function createPost(string $token, Article $article): string|false
    {
        $response = $this->http->withToken($token)->post(
            '/posts', [
                'author' => $article->author,
                'commentary' => $article->text,
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                ],
                'content' => [
                    'article' => array_merge(
                        [
                            'source' => $article->link,
                            'title' => $article->title,
                        ],
                        $article->image ? ['thumbnail' => $article->image] : [],
                    ),
                ],
                'visibility' => 'PUBLIC',
                'lifecycleState' => 'PUBLISHED',
            ],
        );

        if ($response->status() !== 201) {
            Log::debug('Linkedin post request error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return $response->header('x-restli-id');
    }

    public function uploadImage(string $token, string $owner, string $path): string|false
    {
        $response = $this->http->withToken($token)->post(
            '/images?action=initializeUpload',
            [
                'initializeUploadRequest' => [
                    'owner' => $owner,
                ],
            ],
        );

        /** @var string|null $imageId */
        $imageId = $response->json('value.image');

        /** @var string|null $uploadUrl */
        $uploadUrl = $response->json('value.uploadUrl');

        if ($imageId === null || $uploadUrl === null) {
            Log::debug('Linkedin image request error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        /** @var string $mime */
        $mime = mime_content_type($path);

        $file = fopen($path, 'r');

        if ($file === false) {
            Log::debug('LinkedIn image file open error', [
                'path' => $path,
            ]);

            return false;
        }

        $response = app('http')->withToken($token)->withBody($file, $mime)->put($uploadUrl); // @phpstan-ignore-line

        fclose($file);

        if ($response->status() !== 201) {
            Log::debug('LinkedIn image upload error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return $imageId;
    }

    /**
     * @return array{
     *     id: mixed,
     *     name: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     thumbnail: string|null,
     * }|null
     */
    public function me(string $token): ?array
    {
        $response = $this->http->withToken($token)->get('/me', [
            'projection' => '(id,firstName,lastName,profilePicture(displayImage~:playableStreams))',
        ]);

        if (!$response->successful()) {
            return null;
        }

        /** @var array<mixed> $user */
        $user = $response->json();

        $preferredLocale = Arr::get($user, 'firstName.preferredLocale.language') . '_' . Arr::get($user, 'firstName.preferredLocale.country');

        /** @var string|null $firstName */
        $firstName = Arr::get($user, sprintf('firstName.localized.%s', $preferredLocale));

        /** @var string|null $lastName */
        $lastName = Arr::get($user, sprintf('lastName.localized.%s', $preferredLocale));

        /** @var string|null $thumbnail */
        $thumbnail = Arr::get($user, 'profilePicture.displayImage~.elements.0.identifiers.0.identifier');

        return [
            'id' => $user['id'],
            'name' => sprintf('%s %s', $firstName, $lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'thumbnail' => $thumbnail,
        ];
    }

    /**
     * @return array<array<mixed>>
     */
    public function getOrganizations(string $token): array
    {
        $response = $this->http->withToken($token)->get('/organizationAcls', [
            'q' => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
            'state' => 'APPROVED',
            'projection' => '(elements*(organization~(localizedName, logoV2(original~:playableStreams(elements*(*))))))',
        ]);

        $elements = $response->json('elements');

        if (empty($elements)) {
            return [];
        }

        $organizations = [];

        /** @var array<array<mixed>> $elements */
        foreach ($elements as $element) {
            $id = Arr::get($element, 'organization');

            $name = Arr::get($element, 'organization~.localizedName');

            $avatar = Arr::get($element, 'organization~.logoV2.original~.elements.0.identifiers.0.identifier');

            $organizations[] = [
                'id' => $id,
                'thumbnail' => $avatar,
                'name' => $name,
            ];
        }

        return $organizations;
    }

    public function revoke(string $token): bool
    {
        $clientId = config('services.linkedin.client_id');

        $clientSecret = config('services.linkedin.client_secret');

        if ($clientId === null || $clientSecret === null) {
            return false;
        }

        app('http')->baseUrl(self::OAUTH_URL)
            ->asForm()
            ->post('revoke', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'token' => $token,
            ]);

        return true;
    }

    public function introspect(string $token): bool
    {
        $clientId = config('services.linkedin.client_id');

        $clientSecret = config('services.linkedin.client_secret');

        if ($clientId === null || $clientSecret === null) {
            return false;
        }

        $response = app('http')->baseUrl(self::OAUTH_URL)
            ->asForm()
            ->post('introspectToken', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'token' => $token,
            ]);

        return $response->json('active') === true;
    }

    public function refresh(string $refreshToken): ?string
    {
        $clientId = config('services.linkedin.client_id');

        $clientSecret = config('services.linkedin.client_secret');

        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $response = app('http')->baseUrl(self::OAUTH_URL)
            ->asForm()
            ->post('accessToken', [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]);

        // will not get new refresh token, refresh token is valid for 365 days
        // need to re-auth after a year
        // @see: https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens

        /** @var string|null $token */
        $token = $response->json('access_token');

        return $token;
    }
}
