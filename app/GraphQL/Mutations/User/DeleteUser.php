<?php

namespace App\GraphQL\Mutations\User;

use App\Builder\ReleaseEventsBuilder;
use App\Events\Entity\Desk\DeskUserRemoved;
use App\Events\Entity\Tenant\UserLeaved;
use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteUser extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): User
    {
        $this->authorize('write', User::class);

        /** @var User|null $target */
        $target = User::find($args['id']);

        if (is_null($target)) {
            throw new NotFoundHttpException();
        }

        /** @var User $manipulator */
        $manipulator = User::find(auth()->user()?->getAuthIdentifier());

        if ($manipulator->getKey() === $target->getKey()) {
            throw new BadRequestHttpException();
        }

        if (!$manipulator->isLevelHigherThan($target)) {
            throw new AccessDeniedHttpException();
        }

        $deskIds = [];

        try {
            DB::transaction(function () use ($target, &$deskIds) {
                $articleIds = $target
                    ->articles()
                    ->has('authors', '=', 1)
                    ->pluck('articles.id')
                    ->toArray();

                Article::whereIn('id', $articleIds)->unsearchable(); // @phpstan-ignore-line

                Article::whereIn('id', $articleIds)->delete();

                $deskIds = $target->desks()->pluck('id')->toArray();

                // @todo broadcast desk_updated event
                $target->desks()->detach();

                if (!$target->delete()) {
                    throw new InternalServerErrorHttpException();
                }

                return $articleIds;
            });

            /** @var Tenant $tenant */
            $tenant = tenant();

            $tenant->users()->detach($target->getKey());
        } catch (Throwable $e) {
            throw new InternalServerErrorHttpException();
        }

        UserActivity::log(
            name: 'team.delete',
            subject: $target,
        );

        UserLeaved::dispatch($tenant->id, $target->id, [
            'webflow_id' => $target->webflow_id,
            'wordpress_id' => $target->wordpress_id,
            'slug' => $target->slug,
        ]);

        /** @var int[] $deskIds */
        foreach ($deskIds as $deskId) {
            DeskUserRemoved::dispatch($tenant->id, $deskId, $target->id);
        }

        $builder = new ReleaseEventsBuilder();

        $builder->handle('user:delete', ['id' => $target->getKey()]);

        return $target;
    }
}
