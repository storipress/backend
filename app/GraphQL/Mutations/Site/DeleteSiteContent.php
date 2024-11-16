<?php

namespace App\GraphQL\Mutations\Site;

use App\GraphQL\Mutations\Mutation;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;
use Exception;

final class DeleteSiteContent extends Mutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): bool
    {
        try {
            Article::removeAllFromSearch();

            Article::query()->delete();

            Desk::query()->delete();

            Tag::query()->delete();
        } catch (Exception $e) {
            return false;
        }

        UserActivity::log(
            name: 'publication.content.delete',
        );

        return true;
    }
}
