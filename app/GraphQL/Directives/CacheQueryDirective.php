<?php

namespace App\GraphQL\Directives;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Execution\Arguments\NamedType;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CacheQueryDirective extends BaseDirective implements FieldMiddleware
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
Cache the result of a resolver.
"""
directive @cacheQuery(
  """
  Set the group of the query.
  """
  group: String!

  """
  Set the duration it takes for the cache to expire in seconds.
  """
  maxAge: Int
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(
            fn (callable $previousResolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
                $maxAge = $this->directiveArgValue('maxAge', 900);

                $group = $this->directiveArgValue('group');

                $fieldName = $resolveInfo->fieldName;

                $cache = $this->cacheRepository->tags([
                    'lighthouse:cache:group:' . $group,
                ]);

                $cacheKey = 'lighthouse:cache:key:' . $fieldName;

                // handle @find and @paginate
                if (property_exists($resolveInfo, 'argumentSet')) {
                    $arguments = $resolveInfo->argumentSet->argumentsWithUndefined();

                    if (isset($arguments['first']) && isset($arguments['page'])) {
                        $cacheKey .= ':' . $arguments['first']->value . ':' . ($arguments['page']->value ?: 1);
                    } else {
                        foreach ($arguments as $argument) {
                            if ($argument->type instanceof NamedType && $argument->type->name === 'ID') {
                                $cacheKey .= ':' . $argument->value;
                            }
                        }
                    }
                }

                // We found a matching value in the cache, so we can just return early
                // without actually running the query
                $value = $cache->get($cacheKey);

                if ($value !== null) {
                    return $value;
                }

                $resolved = $previousResolver($root, $args, $context, $resolveInfo);

                $storeInCache = static function ($result) use ($cacheKey, $maxAge, $cache): void {
                    $cache->put($cacheKey, $result, Carbon::now()->addSeconds($maxAge)); // @phpstan-ignore-line
                };

                Resolved::handle($resolved, $storeInCache);

                return $resolved;
            },
        );
    }
}
