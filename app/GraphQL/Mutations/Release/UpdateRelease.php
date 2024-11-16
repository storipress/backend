<?php

namespace App\GraphQL\Mutations\Release;

use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Release;
use App\Notifications\Site\SiteDeploymentFailedNotification;
use App\Notifications\Site\SiteDeploymentSucceededNotification;
use Illuminate\Support\Arr;
use Segment\Segment;

final class UpdateRelease extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Release
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        $release = Release::find($args['id']);

        if (! ($release instanceof Release)) {
            throw new NotFoundHttpException();
        }

        $updated = $release->update(Arr::except($args, ['id']));

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        $events = [
            'done' => 'tenant_build_finished',
            'error' => 'tenant_build_failed',
        ];

        if (isset($events[$release->state->key])) {
            Segment::track([
                'userId' => (string) $tenant->owner->id,
                'event' => $events[$release->state->key],
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_build_id' => $release->id,
                    'tenant_build_meta' => $release->meta ?: [],
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);

            if ($release->state->key === 'done') {
                $tenant->owner->notify(new SiteDeploymentSucceededNotification($tenant->id, $release->id));
            } elseif ($release->state->key === 'error') {
                $tenant->owner->notify(new SiteDeploymentFailedNotification($tenant->id, $tenant->name, $release->id));
            }
        }

        return $release;
    }
}
