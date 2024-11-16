<?php

namespace App\GraphQL\Mutations\Site;

use App\Events\Entity\Tenant\TenantUpdated;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Jobs\InitializeSite as InitializeSiteJob;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Str;
use Segment\Segment;

final class UpdateSiteInfo extends Mutation
{
    /**
     * Fields that updatable.
     *
     * @var bool[]
     */
    protected array $updatable = [
        'name' => true,
        'description' => true,
        'email' => true,
        'timezone' => true,
        'favicon' => true,
        'socials' => true,
        'workspace' => true,
        'tutorials' => true,
        'lang' => true,
        'permalinks' => true,
        'sitemap' => true,
        'hosting' => true,
        'desk_alias' => true,
        'buildx' => true,
        'paywall_config' => true,
        'prophet_config' => true,
        'custom_site_template' => true,
    ];

    /**
     * @param  array<string, bool|null|string>  $args
     */
    public function __invoke($_, array $args): Tenant
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new BadRequestHttpException();
        }

        $this->authorize('write', Tenant::class);

        $fields = array_intersect_key($args, $this->updatable);

        foreach ($fields as &$field) {
            if (empty($field)) {
                $field = null;
            }
        }

        if (isset($fields['email'])) {
            $fields['email'] = Str::lower(strval($fields['email']));
        }

        $keys = array_keys($fields);

        $origin = $tenant->only($keys);

        if (!$tenant->update($fields)) {
            throw new InternalServerErrorHttpException();
        }

        if ($tenant->wasChanged('workspace')) {
            InitializeSiteJob::dispatch([
                'id' => $tenant->id,
            ]);
        }

        TenantUpdated::dispatch(
            $tenant->id,
            $keys,
        );

        UserActivity::log(
            name: 'publication.update',
            data: [
                'old' => $origin,
                'new' => $fields,
            ],
        );

        if (data_get($fields, 'tutorials.setCustomiseTheme', false)) {
            Segment::track([
                'userId' => (string) auth()->id(),
                'event' => 'tenant_customised_theme',
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        }

        $activations = ['description', 'email', 'socials'];

        foreach ($activations as $key) {
            $data = $fields[$key] ?? null;

            if (!$data) {
                continue;
            }

            Segment::track([
                'userId' => (string) auth()->id(),
                'event' => sprintf('tenant_%s_updated', $key),
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    $key => $data,
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        }

        return $tenant;
    }
}
