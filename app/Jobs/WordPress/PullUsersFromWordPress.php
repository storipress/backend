<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use App\Observers\RudderStackSyncingObserver;
use Generator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Storipress\WordPress\Objects\User as UserObject;

class PullUsersFromWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $wordpressId = null,
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
            $this->wordpressId ?: 'all',
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

        RudderStackSyncingObserver::mute();

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            foreach ($this->users() as $wpUser) {
                if (! empty($wpUser->first_name) || ! empty($wpUser->last_name)) {
                    $names = [
                        $wpUser->first_name,
                        $wpUser->last_name,
                    ];
                } else {
                    $names = explode(' ', $wpUser->name, 2);

                    // Assign a default value (empty string) if the name cannot be split.
                    if (! isset($names[1])) {
                        $names[1] = '';
                    }
                }

                // Find the user by email in the WordPress user data.
                // If the user does not exist, create a new one.
                $user = User::withoutEagerLoads()->firstOrCreate([
                    'email' => $wpUser->email,
                ], [
                    'password' => Hash::make(Str::password()),
                    'first_name' => $names[0],
                    'last_name' => $names[1],
                    'signed_up_source' => sprintf('invite:%s', $tenant->id),
                ]);

                // If the user wasn't just created, update
                // the name using WordPress user data.
                if (! $user->wasRecentlyCreated && (empty($user->first_name) || empty($user->last_name))) {
                    $user->update([
                        'first_name' => $names[0],
                        'last_name' => $names[1],
                    ]);
                }

                // Find the user by ID within the tenant scope.
                // If the user does not exist, create a new one.
                $tenantUser = TenantUser::firstOrCreate([
                    'id' => $user->id,
                ], [
                    'wordpress_id' => $wpUser->id,
                    'role' => 'author',
                ]);

                // If the user is just created, attach the user to tenant.
                if ($tenantUser->wasRecentlyCreated) {
                    $user->tenants()->attach($tenant->id, ['role' => 'author']);
                }

                // If the "wordpress_id" doesn't match the WordPress user
                // ID (which might occur with existing users), update the
                // "wordpress_id" to the correct value.
                if ($tenantUser->wordpress_id !== $wpUser->id) {
                    $tenantUser->update(['wordpress_id' => $wpUser->id]);
                }

                // Set "wordpress_id" to "null" for the users
                // who have the same "wordpress_id" value.
                TenantUser::where('id', '!=', $tenantUser->id)
                    ->where('wordpress_id', '=', $wpUser->id)
                    ->update(['wordpress_id' => null]);

                ingest(
                    data: [
                        'name' => 'wordpress.user.pull',
                        'source_type' => 'user',
                        'source_id' => $user->id,
                        'wordpress_id' => $wpUser->id,
                    ],
                    type: 'action',
                );
            }
        });

        RudderStackSyncingObserver::unmute();
    }

    /**
     * 取得指定 role 的 users。
     *
     * @return Generator<int, UserObject>
     */
    public function users(): Generator
    {
        $api = app('wordpress')->user();

        $arguments = [
            'page' => 1,
            'per_page' => 25,
            'orderby' => 'id',
            'roles' => ['administrator', 'editor', 'author', 'contributor'],
            'context' => 'edit', // use edit mode to get user's role
        ];

        if (is_int($this->wordpressId)) {
            $arguments['include'] = [$this->wordpressId];
        }

        do {
            $users = $api->list($arguments);

            foreach ($users as $user) {
                yield $user;
            }

            $arguments['page']++;
        } while (count($users) === $arguments['per_page']);
    }
}
