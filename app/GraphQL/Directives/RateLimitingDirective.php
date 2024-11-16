<?php

namespace App\GraphQL\Directives;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Symfony\Component\HttpFoundation\Response;

class RateLimitingDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var RateLimiter
     */
    protected $limiter;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(RateLimiter $limiter, Request $request)
    {
        $this->limiter = $limiter;
        $this->request = $request;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sets rate limit to access the field. Does the same as ThrottleRequests Laravel Middleware.
"""
directive @rateLimiting(
    """
    Named preconfigured rate limiter. Requires Laravel 8.x or later.
    """
    name: String!
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        /** @var array<int, array{key: string, maxAttempts: int, decayMinutes: float}> $limits */
        $limits = [];

        /** @var string|null $name */
        $name = $this->directiveArgValue('name');
        if ($name !== null) {
            $limiter = $this->limiter->limiter($name);

            if ($limiter !== null) {
                $limiterResponse = $limiter($this->request);

                if ($limiterResponse instanceof Unlimited) {
                    return;
                }

                if ($limiterResponse instanceof Response) {
                    throw new DirectiveException(
                        "Expected named limiter {$name} to return an array, got instance of " . get_class($limiterResponse),
                    );
                }

                foreach (Arr::wrap($limiterResponse) as $limit) {
                    $limits[] = [
                        'key' => sha1($name . $limit->key),
                        'maxAttempts' => $limit->maxAttempts,
                        'decayMinutes' => $limit->decayMinutes,
                    ];
                }
            }
        }

        $fieldValue->wrapResolver(
            fn (callable $previousResolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver, $limits) {
                $minRemaining = PHP_INT_MAX;

                foreach ($limits as $limit) {
                    // a key pass data to throttle middleware (kernel.php)
                    $remaining = $this->limiter->remaining($limit['key'], $limit['maxAttempts']);

                    if ($minRemaining >= $remaining) {
                        $minRemaining = $remaining;

                        $this->request->offsetSet('VDaiKHWoMmkLCBoKJk5dXOCM', [
                            'maxAttempts' => $limit['maxAttempts'],
                            'decayMinutes' => $limit['decayMinutes'],
                            'remaining' => ($minRemaining === 0) ? 0 : $minRemaining - 1,
                            'availableIn' => $this->limiter->availableIn($limit['key']),
                        ]);
                    }

                    $this->handleLimit(
                        $limit['key'],
                        $limit['maxAttempts'],
                        $limit['decayMinutes'],
                        "{$resolveInfo->parentType}.{$resolveInfo->fieldName}",
                    );
                }

                return $previousResolver($root, $args, $context, $resolveInfo);
            });
    }

    /**
     * Checks throttling limit and records this attempt.
     */
    protected function handleLimit(string $key, int $maxAttempts, float $decayMinutes, string $fieldReference): void
    {
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw new RateLimitException($fieldReference);
        }

        $this->limiter->hit($key, (int) ($decayMinutes * 60));
    }
}
