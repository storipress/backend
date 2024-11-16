<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskDeleted;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class DeleteDesk extends Mutation
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

        if (!($desk instanceof Desk)) {
            throw new NotFoundHttpException();
        }

        $desk->delete();

        DeskDeleted::dispatch($tenant->id, $desk->id);

        UserActivity::log(
            name: 'desk.delete',
            subject: $desk,
        );

        return $desk;
    }
}
