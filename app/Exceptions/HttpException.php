<?php

namespace App\Exceptions;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Symfony\Component\HttpKernel\Exception\HttpException as BaseHttpException;

class HttpException extends BaseHttpException implements ClientAware, ProvidesExtensions
{
    /**
     * @param  array<string, string|string[]>  $contents
     */
    public function __construct(int $code, array $contents = [])
    {
        $message = ErrorCode::getMessage($code, $contents);

        parent::__construct(
            statusCode: 400,
            message: $message,
            code: $code,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getCategory(): string
    {
        return 'http';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, int|string>
     */
    public function getExtensions(): array
    {
        return [
            'code' => $this->getCode(),
        ];
    }
}
