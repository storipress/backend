<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\User;
use App\Notifications\WordPress\WordPressDatabaseDieNotification;
use App\Notifications\WordPress\WordPressRouteNotFoundNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Storipress\WordPress\Exceptions\CannotCreateException;
use Storipress\WordPress\Exceptions\CannotCreateUserException;
use Storipress\WordPress\Exceptions\CannotEditException;
use Storipress\WordPress\Exceptions\CannotUpdateException;
use Storipress\WordPress\Exceptions\CannotViewUserException;
use Storipress\WordPress\Exceptions\ForbiddenException;
use Storipress\WordPress\Exceptions\InvalidUserIdException;
use Storipress\WordPress\Exceptions\NotFoundException;
use Storipress\WordPress\Exceptions\RestForbiddenException;
use Storipress\WordPress\Exceptions\UserEmailExistsException;
use Storipress\WordPress\Exceptions\UsernameExistsException;
use Storipress\WordPress\Exceptions\WordPressException;
use Storipress\WordPress\Exceptions\WpDieException;
use Storipress\WordPress\Objects\User as UserObject;
use Throwable;

use function Sentry\captureException;

class SyncUserToWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $userId = null,
        public bool $skipSynced = false,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->userId ?: 'all',
        );
    }

    /**
     * Handle the given event.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            $query = User::withoutEagerLoads()->with(['parent']);

            if ($this->userId) {
                $query->where('id', '=', $this->userId);
            }

            if ($this->skipSynced) {
                $query->whereNull('wordpress_id');
            }

            foreach ($query->lazyById() as $user) {
                $username = sprintf('storipress%06d', $user->id);

                $params = [
                    'username' => $username,
                    'email' => $user->email,
                    'name' => $user->full_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'slug' => $user->slug,
                    'roles' => 'contributor',
                    'password' => Str::password(symbols: false), // dummy data, required by the API.
                ];

                try {
                    $wpUser = $this->createOrUpdateUser($user->wordpress_id, $params);
                } catch (UsernameExistsException|UserEmailExistsException $e) {
                    $users = app('wordpress')->user()->list([
                        'search' => ($e instanceof UserEmailExistsException) ? $user->email : $username,
                    ]);

                    if (empty($users)) {
                        captureException($e);
                    } else {
                        $user->update([
                            'wordpress_id' => $users[0]->id,
                        ]);
                    }

                    continue;
                } catch (
                    CannotCreateException|
                    CannotCreateUserException|
                    CannotEditException|
                    CannotUpdateException|
                    CannotViewUserException|
                    NotFoundException|
                    RestForbiddenException|
                    ForbiddenException
                ) {
                    $wordpress->config->update(['expired' => true]);

                    $tenant->owner->notify(
                        new WordPressRouteNotFoundNotification(
                            $tenant->id,
                            $tenant->name,
                        ),
                    );

                    break;
                } catch (WpDieException) {
                    $tenant->owner->notify(
                        new WordPressDatabaseDieNotification(
                            $tenant->id,
                            $tenant->name,
                        ),
                    );

                    break;
                } catch (Throwable $e) {
                    captureException($e);

                    break;
                }

                $user->update([
                    'wordpress_id' => $wpUser->id,
                ]);

                ingest(
                    data: [
                        'name' => 'wordpress.user.sync',
                        'source_type' => 'user',
                        'source_id' => $this->userId,
                        'wordpress_id' => $user->wordpress_id,
                    ],
                    type: 'action',
                );
            }
        });
    }

    /**
     * @param array{
     *     username: string,
     *     name: ?string,
     *     first_name: ?string,
     *     last_name: ?string,
     *     slug: ?string,
     *     email: string,
     *     roles: string,
     *     password: string,
     * } $params
     *
     * @throws WordPressException
     */
    public function createOrUpdateUser(?int $id, array $params): UserObject
    {
        $api = app('wordpress')->user();

        if (is_int($id)) {
            try {
                return $api->update($id, Arr::only($params, [
                    'name', 'first_name', 'last_name',
                ]));
            } catch (NotFoundException|InvalidUserIdException) {
                // ignored
            }
        }

        return $api->create($params);
    }
}
