<?php

namespace App\Jobs\Tenants\Database;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\Bouncer;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

final class CreateDefaultTenantBouncers implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->tenant->run(function () {
            /** @var Bouncer $bouncer */
            $bouncer = app(Bouncer::class);

            $role = $this->getRole();

            $ability = $this->getAbility();

            $groups = ['role', 'ability'];

            $now = now();

            foreach ($groups as $group) {
                $rows = [];

                foreach ($$group as $name => $data) {
                    $rows[] = array_merge($data, [
                        'name' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table($bouncer->{$group}()->getTable())->insert($rows);
            }

            $bouncer->allow('owner')->everything();

            $bouncer->allow('admin')->to($this->getAdminAbility());

            $bouncer->allow('editor')->to($this->getEditorAbility());

            $bouncer->allow('author')->to($this->getAuthorAbility());

            $bouncer->allow('contributor')->to($this->getContributorAbility());
        });
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    protected function getRole(): array
    {
        return [
            'owner' => [
                'title' => 'Site Owner',
                'level' => (2 ** 32) - 1,
            ],
            'admin' => [
                'title' => 'Administrator',
                'level' => 4096,
            ],
            'editor' => [
                'title' => 'Editor',
                'level' => 1024,
            ],
            'author' => [
                'title' => 'Author',
                'level' => 256,
            ],
            'contributor' => [
                'title' => 'Contributor',
                'level' => 64,
            ],
        ];
    }

    /**
     * @return string[][]
     */
    protected function getAbility(): array
    {
        return [
            'site:update' => [
                'title' => 'update site info(exclude custom domain)',
            ],
            'site:domain:update' => [
                'title' => 'update site custom domain',
            ],
            'billing:update' => [
                'title' => 'update billing info',
            ],
            'integration:update' => [
                'title' => 'update integration setting',
            ],
            'insight:view' => [
                'title' => 'view site insights',
            ],
            'user:invite' => [
                'title' => 'invite new user',
            ],
            'user:suspend' => [
                'title' => 'suspend an user',
            ],
            'user:unsuspend' => [
                'title' => 'unsuspend an user',
            ],
            'user:profile:update' => [
                'title' => 'update user profile',
            ],
            'user:role:change' => [
                'title' => 'change user role',
            ],
            'user:delete' => [
                'title' => 'delete user',
            ],
            'design:update' => [
                'title' => 'update design',
            ],
            'page:create' => [
                'title' => 'create a new page',
            ],
            'page:update' => [
                'title' => 'update page design',
            ],
            'page:delete' => [
                'title' => 'delete page',
            ],
            'layout:create' => [
                'title' => 'create a new layout',
            ],
            'layout:update' => [
                'title' => 'update layout design',
            ],
            'layout:delete' => [
                'title' => 'delete layout',
            ],
            'stage:create' => [
                'title' => 'create a new stage',
            ],
            'stage:update' => [
                'title' => 'update stage info',
            ],
            'stage:delete' => [
                'title' => 'delete stage',
            ],
            'desk:create' => [
                'title' => 'create new desk',
            ],
            'desk:update' => [
                'title' => 'update desk info',
            ],
            'desk:delete' => [
                'title' => 'delete desk',
            ],
            'desk:user:assign' => [
                'title' => 'assign user to desk',
            ],
            'desk:user:revoke' => [
                'title' => 'revoke user from desk',
            ],
            'desk:article:create' => [
                'title' => 'create new article within assigned desks',
            ],
            'desk:article:update' => [
                'title' => 'update article content within assigned desks',
            ],
            'desk:article:schedule' => [
                'title' => 'un/schedule and un/publish article within assigned desks',
            ],
            'desk:article:delete' => [
                'title' => 'delete article within assigned desks',
            ],
            'desk:article:stage:change' => [
                'title' => 'change article stage within assigned desks',
            ],
            'article:create' => [
                'title' => 'create new article in all desks',
            ],
            'article:update' => [
                'title' => 'update article content in all desks',
            ],
            'article:schedule' => [
                'title' => 'un/schedule and un/publish article in all desks',
            ],
            'article:delete' => [
                'title' => 'delete article in all desks',
            ],
            'article:stage:change' => [
                'title' => 'change article stage in all desks',
            ],
        ];
    }

    /**
     * @return string[]
     */
    protected function getAdminAbility(): array
    {
        return [
            'site:update',
            'site:domain:update',
            'integration:update',
            'insight:view',
            'user:invite',
            'user:suspend',
            'user:unsuspend',
            'user:profile:update',
            'user:role:change',
            'user:delete',
            'design:update',
            'page:create',
            'page:update',
            'page:delete',
            'layout:create',
            'layout:update',
            'layout:delete',
            'stage:create',
            'stage:update',
            'stage:delete',
            'desk:create',
            'desk:update',
            'desk:delete',
            'desk:user:assign',
            'desk:user:revoke',
            'desk:article:create',
            'desk:article:update',
            'desk:article:schedule',
            'desk:article:delete',
            'desk:article:stage:change',
            'article:create',
            'article:update',
            'article:schedule',
            'article:delete',
            'article:stage:change',
        ];
    }

    /**
     * @return string[]
     */
    protected function getEditorAbility(): array
    {
        return [
            'user:invite',
            'user:profile:update',
            'user:role:change',
            'desk:create',
            'desk:update',
            'desk:delete',
            'desk:user:assign',
            'desk:user:revoke',
            'desk:article:create',
            'desk:article:update',
            'desk:article:schedule',
            'desk:article:delete',
            'desk:article:stage:change',
        ];
    }

    /**
     * @return string[]
     */
    protected function getAuthorAbility(): array
    {
        return [
            'desk:user:assign',
            'desk:user:revoke',
            'desk:article:create',
            'desk:article:update',
            'desk:article:schedule',
            'desk:article:delete',
            'desk:article:stage:change',
        ];
    }

    /**
     * @return string[]
     */
    protected function getContributorAbility(): array
    {
        return [
            'desk:user:assign',
            'desk:article:create',
            'desk:article:update',
            'desk:article:delete',
        ];
    }
}
