<?php

namespace App\GraphQL\Mutations\Design;

use App\Events\Entity\Design\DesignUpdated;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Design;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

final class UpdateDesign extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Design
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Design::class);

        $design = Design::find($args['key']);

        if (! ($design instanceof Design)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['key']);

        $origin = $design->only(array_keys($attributes));

        $design->update($attributes);

        DesignUpdated::dispatch($tenant->id, $design->key, array_keys($attributes));

        UserActivity::log(
            name: 'design.update',
            data: [
                'key' => $design->getKey(),
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $design;
    }
}
