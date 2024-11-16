<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorException extends HttpException
{
    /**
     * @param  array<string, string|string[]>  $contents
     */
    public function __construct(int $code, array $contents = [], int $statusCode = 200)
    {
        $message = ErrorCode::getMessage($code, $contents);

        parent::__construct(
            statusCode: $statusCode,
            message: $message,
            code: $code,
        );
    }
}
