<?php

namespace App\GraphQL\Queries;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\User;
use Illuminate\Support\Facades\Route;
use JsonException;
use Typesense\LaravelTypesense\Typesense;

class ArticleSearchKey
{
    /**
     * @param  array<string, mixed>  $args
     *
     * @throws JsonException
     */
    public function __invoke($_, array $args): string
    {
        /** @var string $key */
        $key = config('scout.typesense.search_only_key');

        return app(Typesense::class)
            ->getClient()
            ->getKeys()
            ->generateScopedSearchKey(
                $key,
                [
                    'collection' => (new Article())->searchableAs(),
                    'filter_by' => $this->filterBy(),
                    'expires_at' => now()->addMonths()->timestamp,
                ],
            );
    }

    protected function filterBy(): string
    {
        $empty = 'id:0';

        if (Route::current()?->getName() === 'graphql.central') {
            return $empty;
        }

        /** @var Tenant $tenant */
        $tenant = tenant();

        if (!$tenant->initialized) {
            return $empty;
        }

        /** @var User|null $user */
        $user = User::find(auth()->user()?->getAuthIdentifier());

        if (is_null($user)) {
            return $empty;
        }

        return '';

        // if ($user->isAdmin()) {
        //     return '';
        // }
        //
        // $deskIds = $user->desks->pluck('id')->implode(',');
        //
        // $filterBy = sprintf('desk_id:[%s]', $deskIds);
        //
        // if ($user->role === 'editor') {
        //     return $filterBy;
        // }
        //
        // return sprintf('%s && author_ids:[%d]', $filterBy, (int) $user->getKey());
    }
}
