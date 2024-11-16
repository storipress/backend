<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskUpdated;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

final class UpdateDesk extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Desk
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Desk::class);

        $desk = Desk::find($args['id']);

        if (! ($desk instanceof Desk)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        $origin = $desk->only(array_keys($attributes));

        $slug = $desk->slug;

        if (! isset($attributes['name'])) {
            unset($attributes['name']);
        } else {
            if (empty(trim($attributes['name']))) {
                unset($attributes['name']);
            } else {
                if (! array_key_exists('slug', $attributes)) {
                    $desk->slug = '';
                }
            }
        }

        $desk->update($attributes);

        $desk->refresh();

        if ($desk->slug !== $slug) {
            $attributes['slug'] = $desk->slug;
        }

        DeskUpdated::dispatch($tenant->id, $desk->id, array_keys($attributes));

        UserActivity::log(
            name: 'desk.update',
            subject: $desk,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $desk;
    }
}
