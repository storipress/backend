<?php

namespace App\GraphQL\Mutations\Generator;

use App\Builder\ReleaseEventsBuilder;
use App\Enums\Release\Type;
use App\Events\Entity\Article\ArticlePublished;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use Webmozart\Assert\Assert;

final class TriggerSiteBuild extends Mutation
{
    /**
     * @param  array{
     *     id?: string,
     *     type?: Type,
     * }  $args
     */
    public function __invoke($_, array $args): ?int
    {
        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        /** @var string $tenantId */
        $tenantId = $tenant->getKey();

        $builder = new ReleaseEventsBuilder();

        if (isset($args['id'], $args['type']) && Type::article()->is($args['type'])) {
            $articleId = intval($args['id']);

            ArticlePublished::dispatch($tenantId, $articleId);

            $release = $builder->handle('article:build', ['id' => $articleId]);
        } else {
            $release = $builder->handle('site:build');
        }

        return $release?->id;
    }
}
