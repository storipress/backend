<?php

namespace App\GraphQL\Mutations\Layout;

use App\Events\Entity\Layout\LayoutDeleted;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Layout;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class DeleteLayout extends Mutation
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

        if (! ($layout instanceof Layout)) {
            throw new NotFoundHttpException();
        }

        $layout->delete();

        LayoutDeleted::dispatch($tenant->id, $layout->id);

        UserActivity::log(
            name: 'layout.delete',
            subject: $layout,
        );

        return $layout;
    }
}
