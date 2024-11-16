<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenants\Integration;
use App\SDK\LinkedIn\LinkedIn;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePlatformsProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:profiles:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update platforms profiles';

    /**
     * Execute the console command.
     */
    public function handle(Linkedin $linkedinClient): void
    {
        $platforms = [
            'linkedin' => $linkedinClient,
        ];

        runForTenants(function () use ($platforms) {
            $keys = array_keys($platforms);

            $integrations = Integration::whereIn('key', $keys)
                ->whereNotNull('internals')
                ->get();

            $connected = $integrations->mapWithKeys(
                fn ($item) => [$item->key => $item],
            );

            foreach ($platforms as $key => $client) {
                if (! $connected->has($key)) {
                    continue;
                }

                $app = $connected->get($key);

                $name = 'LinkedIn';

                $method = sprintf('update%sProfiles', $name);

                if (! method_exists($this, $method)) {
                    continue;
                }

                $this->{$method}($client, $app);
            }
        });
    }

    public function updateLinkedInProfiles(LinkedIn $client, Integration $linkedin): bool
    {
        /** @var array{
         *     id: string,
         *     thumbnail: string,
         *     access_token: string,
         *     refresh_token: string,
         *     authors: array{array{
         *      id: string,
         *      name: string,
         *      thumbnail: string
         *     }}
         * } $configuration
         */
        $configuration = $linkedin->internals;

        $token = $configuration['access_token'];

        $refreshToken = $configuration['refresh_token'];

        // token expired
        if (! $client->introspect($token)) {
            // refresh token expired
            if (! $client->introspect($refreshToken)) {
                // revoke integration
                $linkedin->revoke();

                $this->slackLog(
                    'debug',
                    '[Update Profile] Auto revoke linkedin integration',
                    [
                        'tenant' => tenant('id'),
                    ],
                );

                return false;
            }

            $accessToken = $client->refresh($refreshToken);

            if ($accessToken === null) {
                $this->slackLog(
                    'debug',
                    '[Update Profile] Can not refresh linkedin token',
                    [
                        'tenant' => tenant('id'),
                    ],
                );

                return false;
            }

            $internals = $linkedin->internals;

            $internals['access_token'] = $accessToken;

            $linkedin->internals = $internals;

            $linkedin->save();
        }

        /** @var array{id: string, name: string, email: string|null, thumbnail: string|null}|null $user */
        $user = $client->me($token);

        if (empty($user)) {
            $this->slackLog(
                'debug',
                '[Update Profile] Unexpected linkedin error',
                [
                    'client' => tenant('id'),
                ],
            );

            return false;
        }

        $organizations = $client->getOrganizations($token);

        $configuration['name'] = $user['name'];

        $configuration['thumbnail'] = $user['thumbnail'];

        $configuration['authors'] = [
            [
                'id' => sprintf('urn:li:person:%s', $user['id']),
                'name' => $user['name'],
                'thumbnail' => $user['thumbnail'],
            ],
            ...$organizations,
        ];

        $linkedin->internals = $configuration;

        return $linkedin->save();
    }

    /**
     * @param  array<mixed>  $contents
     */
    protected function slackLog(string $type, string $message, array $contents): void
    {
        // Don't notify if the environment is 'testing' or 'local'
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        if (! in_array($type, ['error', 'debug'])) {
            $type = 'debug';
        }

        Log::channel('slack')->$type(
            $message,
            array_merge(['env' => app()->environment()], $contents),
        );
    }
}
