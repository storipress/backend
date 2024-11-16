<?php

namespace App\GraphQL\Mutations\Desk;

use App\Events\Entity\Desk\DeskOrderChanged;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Exception;
use Webmozart\Assert\Assert;

class MoveDeskAfter
{
    /**
     * @param  array{
     *     id: string,
     *     target_id: string,
     * }  $args
     *
     * @throws Exception
     */
    public function __invoke($_, array $args): Desk
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $desk = Desk::find($args['id']);

        $target = Desk::find($args['target_id']);

        if (!($desk instanceof Desk) || !($target instanceof Desk)) {
            throw new NotFoundHttpException();
        }

        if ($desk->desk_id !== $target->desk_id) {
            throw new BadRequestHttpException();
        }

        $original = $desk->order;

        $desk->moveAfter($target);

        DeskOrderChanged::dispatch($tenant->id, $desk->id);

        UserActivity::log(
            name: 'desk.order.change',
            subject: $desk,
            data: [
                'old' => $original,
                'new' => $desk->order,
            ],
        );

        return $desk;
    }
}
