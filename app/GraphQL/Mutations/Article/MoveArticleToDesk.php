<?php

namespace App\GraphQL\Mutations\Article;

use App\Events\Entity\Article\ArticleDeskChanged;
use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class MoveArticleToDesk extends ArticleMutation
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): Article
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $article = Article::find($args['id']);

        if (is_null($article)) {
            throw new NotFoundHttpException();
        }

        $this->authorize('write', $article);

        $desk = Desk::find($args['desk_id']);

        if ($desk === null) {
            throw new InternalServerErrorHttpException();
        }

        /** @var User $user */
        $user = User::find(auth()->user()?->getAuthIdentifier());

        if (!(
            $desk->open_access ||
            $user->isInDesk($desk) ||
            ($desk->desk && $user->isInDesk($desk->desk))
        )) {
            throw new AccessDeniedHttpException();
        }

        $originDeskId = $article->desk_id;

        $article->desk()->associate($desk);

        $article->save();

        ArticleDeskChanged::dispatch($tenant->id, $article->id, $originDeskId);

        UserActivity::log(
            name: 'article.desk.change',
            subject: $article,
            data: [
                'old' => $originDeskId,
                'new' => $desk->id,
            ],
        );

        return $article;
    }
}
