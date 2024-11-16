<?php

namespace App\GraphQL\Mutations\Layout;

use App\Events\Entity\Layout\LayoutUpdated;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Layout;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

final class UpdateLayout extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): Layout
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $this->authorize('write', Layout::class);

        $layout = Layout::find($args['id']);

        if (!($layout instanceof Layout)) {
            throw new NotFoundHttpException();
        }

        $attributes = Arr::except($args, ['id']);

        $origin = $layout->only(array_keys($attributes));

        $layout->update($attributes);

        LayoutUpdated::dispatch($tenant->id, $layout->id, array_keys($attributes));

        UserActivity::log(
            name: 'layout.update',
            subject: $layout,
            data: [
                'old' => $origin,
                'new' => $attributes,
            ],
        );

        return $layout;
    }
}
