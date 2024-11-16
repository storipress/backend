<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Notifications\Twitter\TwitterUnauthorizedNotification;
use Storipress\Twitter\Exceptions\InvalidRefreshToken;

class RefreshTwitterProfile extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'refresh-twitter-profile {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $client = config('services.twitter.client_id');

        $secret = config('services.twitter.client_secret');

        if (! is_not_empty_string($client) || ! is_not_empty_string($secret)) {
            return static::SUCCESS;
        }

        $tenants = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->whereJsonContainsKey('data->twitter_data->refresh_token');

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        runForTenants(function (Tenant $tenant) use ($client, $secret) {
            if ($tenant->twitter_data === null) {
                return;
            }

            $integration = Integration::find('twitter');

            if (! ($integration instanceof Integration)) {
                return;
            }

            $twitter = app('twitter');

            try {
                $token = $twitter->refreshToken()->obtain(
                    $client,
                    $secret,
                    $tenant->twitter_data['refresh_token'],
                );

                $expiresOn = now()
                    ->addSeconds($token->expires_in)
                    ->subMinute()
                    ->getTimestamp();

                $me = $twitter->setToken($token->access_token)->me()->get([
                    'user.fields' => 'profile_image_url',
                ]);
            } catch (InvalidRefreshToken) {
                $tenant->owner->notify(
                    new TwitterUnauthorizedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );

                $tenant->update(['twitter_data' => null]);

                $integration->reset();

                return;
            }

            $tenant->update([
                'twitter_data' => [
                    'user_id' => $me->id,
                    'expires_on' => $expiresOn,
                    'access_token' => $token->access_token,
                    'refresh_token' => $token->refresh_token,
                ],
            ]);

            $integration->update([
                'data' => [
                    [
                        'user_id' => $me->id,
                        'name' => $me->name,
                        'thumbnail' => $me->profile_image_url,
                    ],
                ],
                'internals' => [
                    'user_id' => $me->id,
                    'name' => $me->name,
                    'username' => $me->username,
                    'thumbnail' => $me->profile_image_url,
                    'scopes' => array_values(explode(' ', $token->scope)),
                    'expires_on' => $expiresOn,
                    'access_token' => $token->access_token,
                    'refresh_token' => $token->refresh_token,
                ],
                'updated_at' => now(),
            ]);
        }, $tenants->lazyById(50));

        return static::SUCCESS;
    }
}
