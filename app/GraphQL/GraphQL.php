<?php

namespace App\GraphQL;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use Illuminate\Support\Facades\Gate;

abstract class GraphQL
{
    /**
     * Determine if the given ability should be granted for the current user.
     *
     * @param  array|mixed  $arguments
     *
     * @throws HttpException
     */
    protected function authorize(string $abilities, mixed $arguments = []): void
    {
        if ($this->cant($abilities, $arguments)) {
            throw new HttpException(ErrorCode::PERMISSION_FORBIDDEN);
        }
    }

    /**
     * Determine if the given abilities should be granted for the current user.
     *
     * @param  array|mixed  $arguments
     */
    protected function can(string $abilities, mixed $arguments = []): bool
    {
        return Gate::check($abilities, $arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user.
     *
     * @param  array|mixed  $arguments
     */
    protected function cant(string $abilities, mixed $arguments = []): bool
    {
        return ! $this->can($abilities, $arguments);
    }
}
