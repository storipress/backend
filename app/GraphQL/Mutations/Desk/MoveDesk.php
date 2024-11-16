<?php

namespace App\GraphQL\Mutations\Desk;

use App\Console\Migrations\MigrateDeskCounter;
use App\Events\Entity\Desk\DeskHierarchyChanged;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Rutorika\Sortable\SortableException;

class MoveDesk extends Mutation
{
    /**
     * @param  array{
     *     id: string,
     *     target_id: string|null,
     *     before_id?: string|null,
     *     after_id?: string|null,
     * }  $args
     *
     * @throws SortableException
     * @throws \Exception
     */
    public function __invoke($_, array $args): Desk
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::DESK_NOT_FOUND);
        }

        $this->authorize('write', Desk::class);

        $desk = Desk::withoutEagerLoads()->find($args['id']);

        if (!($desk instanceof Desk)) {
            throw new HttpException(ErrorCode::DESK_NOT_FOUND);
        }

        $targetId = $args['target_id'];

        if ($targetId !== null) {
            $targetId = (int) $targetId;

            if ($desk->id === $targetId) {
                throw new HttpException(ErrorCode::DESK_MOVE_TO_SELF);
            }

            if ($desk->desks()->count() > 0) {
                throw new HttpException(ErrorCode::DESK_HAS_SUB_DESKS);
            }

            $root = Desk::withoutEagerLoads()->find($targetId);

            if (!($root instanceof Desk)) {
                throw new HttpException(ErrorCode::DESK_NOT_FOUND);
            }

            if ($root->desks()->count() === 0) {
                $sub = Desk::create([
                    'name' => $root->name,
                    'desk_id' => $root->id,
                ]);

                $root->articles()->update(['desk_id' => $sub->id]);

                $sub->articles()->chunkById(100, fn ($articles) => $articles->searchable());
            }
        }

        $desk->update([
            'desk_id' => $targetId,
            'order' => Desk::max('order') + 1,
        ]);

        if (!empty($args['before_id']) && ($before = Desk::withoutEagerLoads()->find($args['before_id']))) {
            if ($before->desk_id === $desk->desk_id) {
                $desk->moveBefore($before);
            }
        } elseif (!empty($args['after_id']) && ($after = Desk::withoutEagerLoads()->find($args['after_id']))) {
            if ($after->desk_id === $desk->desk_id) {
                $desk->moveAfter($after);
            }
        }

        $desk->articles()->chunkById(100, fn ($articles) => $articles->searchable());

        Artisan::queue(MigrateDeskCounter::class, ['tenant' => tenant('id')]);

        DeskHierarchyChanged::dispatch($tenant->id, $desk->id);

        UserActivity::log(
            name: 'desk.move',
            subject: $desk,
            data: Arr::only($args, [
                'target_id',
                'before_id',
                'after_id',
            ]),
        );

        return $desk->refresh();
    }
}
