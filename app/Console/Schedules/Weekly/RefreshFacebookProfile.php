<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\Notifications\Facebook\FacebookUnauthorizedNotification;
use Illuminate\Support\Arr;
use stdClass;
use Storipress\Facebook\Exceptions\ExpiredAccessToken;

class RefreshFacebookProfile extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'refresh-facebook-profile {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $secret = config('services.facebook.client_secret');

        if (! is_not_empty_string($secret)) {
            return static::SUCCESS;
        }

        $tenants = Tenant::withoutEagerLoads()
            ->with(['owner'])
            ->initialized()
            ->whereJsonContainsKey('data->facebook_data->access_token');

        if (! empty($this->option('tenants'))) {
            $tenants->whereIn('id', $this->option('tenants'));
        }

        runForTenants(function (Tenant $tenant) use ($secret) {
            if ($tenant->facebook_data === null) {
                return;
            }

            $integration = Integration::find('facebook');

            if (! ($integration instanceof Integration)) {
                return;
            }

            $token = $tenant->facebook_data['access_token'];

            try {
                $me = app('facebook')
                    ->setDebug('all')
                    ->setSecret($secret)
                    ->setUserToken($token)
                    ->me()
                    ->get([
                        'fields' => implode(',', [
                            'id',
                            'name',
                            'picture.type(large){url}',
                            'accounts.limit(100){id,name,picture.type(large){url},access_token}',
                            'permissions',
                        ]),
                    ]);
            } catch (ExpiredAccessToken) {
                $tenant->owner->notify(
                    new FacebookUnauthorizedNotification(
                        $tenant->id,
                        $tenant->name,
                    ),
                );

                $tenant->update(['facebook_data' => null]);

                $integration->reset();

                return;
            }

            if (! isset($me->accounts->data) || ! is_array($me->accounts->data) || empty($me->accounts->data)) {
                return; // @todo facebook - missing permission, or no pages have been granted access
            }

            $permissions = array_map(
                fn (stdClass $permission) => $permission->status === 'granted'
                    ? $permission->permission
                    : null,
                $me->permissions->data, // @phpstan-ignore-line
            );

            $pages = Arr::mapWithKeys(
                $me->accounts->data,
                fn (stdClass $data) => [
                    $data->id => [
                        'page_id' => $data->id,
                        'name' => $data->name,
                        'thumbnail' => $data->picture->data->url,
                        'access_token' => $data->access_token,
                    ],
                ],
            );

            $integration->update([
                'data' => array_values(
                    array_map(
                        fn (array $page) => Arr::except($page, ['access_token']),
                        $pages,
                    ),
                ),
                'internals' => [
                    'user_id' => $me->id,
                    'name' => $me->name,
                    'scopes' => array_values(array_filter($permissions)),
                    'access_token' => $token,
                    'pages' => $pages,
                ],
                'updated_at' => now(),
            ]);
        }, $tenants->lazyById(50));

        return static::SUCCESS;
    }
}
