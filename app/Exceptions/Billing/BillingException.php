<?php

namespace App\Exceptions\Billing;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class BillingException extends HttpException implements ClientAware, ProvidesExtensions
{
    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     *
     * @api
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     *
     * @api
     */
    public function getCategory(): string
    {
        return 'billing';
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     *
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
