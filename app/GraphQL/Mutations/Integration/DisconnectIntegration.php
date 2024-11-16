<?php

namespace App\GraphQL\Mutations\Integration;

use App\Events\Entity\Integration\IntegrationDisconnected;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Models\Tenants\UserActivity;
use App\SDK\LinkedIn\LinkedIn;
use App\SDK\Slack\Slack;
use Illuminate\Support\Arr;
use Storipress\Facebook\Exceptions\FacebookException;
use Storipress\Twitter\Exceptions\InvalidRefreshToken;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

final class DisconnectIntegration extends IntegrationMutation
{
    /**
     * @var string[]
     */
    protected array $platforms = [
        'facebook' => 'facebook',
        'twitter' => 'twitter',
        'slack' => 'slack',
        'shopify' => 'shopify',
        'linkedin' => 'linkedIn',
    ];

    protected Tenant $tenant;

    /**
     * @param  array{
     *     key: string,
     * } $args
     */
    public function __invoke($_, array $args): Integration
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->tenant = $tenant;

        $this->authorize('write', Integration::class);

        if (!isset($this->platforms[$args['key']])) {
            throw new BadRequestHttpException();
        }

        $key = $args['key'];

        IntegrationDisconnected::dispatch($tenant->id, $key);

        UserActivity::log(
            name: 'integration.disconnect',
            data: [
                'key' => $key,
            ],
        );

        if ($key === 'shopify') {
            return $this->shopifyDisconnect();
        }

        $method = sprintf('%sDisconnect', $this->platforms[$key]);

        if (method_exists($this, $method)) {
            $this->{$method}();
        }

        $tenant->{$key . '_data'} = null;

        $tenant->save();

        $updated = $this->update($key,
            [
                'data' => [],
                'internals' => null,
                'activated_at' => null,
                'updated_at' => now(),
            ],
        );

        return $updated;
    }

    protected function facebookDisconnect(): void
    {
        if ($this->tenant->facebook_data === null) {
            return;
        }

        $secret = config('services.facebook.client_secret');

        if (!is_not_empty_string($secret)) {
            return;
        }

        try {
            app('facebook')
                ->setDebug('all')
                ->setSecret($secret)
                ->setUserToken($this->tenant->facebook_data['access_token'])
                ->permission()
                ->delete($this->tenant->facebook_data['user_id']);
        } catch (FacebookException) {
            // ignored
        } catch (Throwable $e) {
            captureException($e);
        }
    }

    protected function twitterDisconnect(): void
    {
        if ($this->tenant->twitter_data === null) {
            return;
        }

        $client = config('services.twitter.client_id');

        $secret = config('services.twitter.client_secret');

        if (!is_not_empty_string($client) || !is_not_empty_string($secret)) {
            return;
        }

        try {
            app('twitter')->refreshToken()->revoke(
                $client,
                $secret,
                $this->tenant->twitter_data['refresh_token'],
                'refresh_token',
            );
        } catch (InvalidRefreshToken) {
            // ignored
        } catch (Throwable $e) {
            captureException($e);
        }
    }

    protected function slackDisconnect(): void
    {
        /** @var array{team_id:string}|null $data */
        $data = $this->tenant->slack_data;

        $teamId = !empty($data) ? $data['team_id'] : '';

        /** @var Integration $integraion */
        $integraion = Integration::find('slack');

        $internals = $integraion->internals;

        $internals = is_array($internals) ? $internals : [];

        /** @var string $botToken */
        $botToken = Arr::get($internals, 'bot_access_token', '');

        if (!empty($botToken) && !Tenant::whereJsonContains('data->slack_data->team_id', $teamId)->exists()) {
            (new Slack())->uninstall($botToken);
        }
    }

    protected function linkedInDisconnect(): void
    {
        $accessToken = $this->tenant->run(function () {
            $integration = Integration::find('linkedin');

            if ($integration === null) {
                return null;
            }

            return Arr::get($integration->internals ?: [], 'access_token');
        });

        if (!is_not_empty_string($accessToken)) {
            throw new ErrorException(ErrorCode::LINKEDIN_INTEGRATION_NOT_CONNECT);
        }

        (new LinkedIn())->revoke($accessToken);
    }

    protected function shopifyDisconnect(): Integration
    {
        return $this->update(
            'shopify',
            [
                'activated_at' => null,
            ],
        );
    }

    protected function isOtherTenantsHasSameTeamId(string $teamId): bool
    {
        $tenants = Tenant::initialized()
            ->whereNot('id', '=', $this->tenant->id)
            ->lazyById();

        foreach ($tenants as $tenant) {
            /** @var array{team_id:string}|null $data */
            $data = $tenant->slack_data;

            if ($data === null) {
                continue;
            }

            if ($teamId === $data['team_id']) {
                return true;
            }
        }

        return false;
    }
}
