<?php

namespace App\GraphQL\Directives;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class ClearCacheQueryDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var CacheRepository
     */
    protected $cacheRepository;

    public function __construct(CacheRepository $cacheRepository)
    {
        $this->cacheRepository = $cacheRepository;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Clear a resolver cache by tags.
"""
directive @clearCacheQuery(
  """
  Group of the cached query to be cleared.
  """
  group: String!
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->resultHandler(
            function ($result) {
                $group = $this->directiveArgValue('group');

                $this->cacheRepository->tags(['lighthouse:cache:group:' . $group])->flush();

                return $result;
            },
        );
    }
}
