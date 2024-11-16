<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

final class GraphQLHttpMethodNotAllowed
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedMethods = ['POST'];

        $method = $request->getMethod();

        if (!in_array($method, $allowedMethods)) {
            $this->methodNotAllowed($allowedMethods, $method);
        }

        return $next($request);
    }

    /**
     * @param  array<string>  $others
     */
    protected function methodNotAllowed(array $others, string $method): void
    {
        throw new MethodNotAllowedHttpException(
            $others,
            sprintf(
                'The %s method is not supported for this route. Supported methods: %s.',
                $method,
                implode(', ', $others),
            ),
        );
    }
}
