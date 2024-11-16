<?php

namespace App\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as BaseNotFoundHttpException;

final class NotFoundHttpException extends BaseNotFoundHttpException implements ClientAware, ProvidesExtensions
{
    /**
     * InvalidCredentialsException constructor.
     */
    public function __construct()
    {
        parent::__construct('Not Found.');
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
