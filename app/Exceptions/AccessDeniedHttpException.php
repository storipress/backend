<?php

namespace App\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException as BaseAccessDeniedHttpException;

final class AccessDeniedHttpException extends BaseAccessDeniedHttpException implements ClientAware, ProvidesExtensions
{
    /**
     * InvalidCredentialsException constructor.
     */
    public function __construct()
    {
        parent::__construct('Forbidden.');
    }

    /**
     * Returns true when exception message is
     * safe to be displayed to a client.
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced
     * by query parsing or validation, do not use it.
     */
    public function getCategory(): string
    {
        return 'http';
    }

    /**
     * Return the content that is put in the
     * "extensions" part of the returned error.
     *
     * @return array<string>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
