<?php

namespace App\GraphQL\Queries;

use App\Exceptions\UnexpectedHttpException;
use Exception;

use function Sentry\captureException;

final class UnsplashDownload
{
    /**
     * @param  array<string, string>  $args
     *
     * @throws \Exception
     */
    public function __invoke($_, array $args): string
    {
        /**
         * 500, 503 	Something went wrong on our end
         *
         * @link https://unsplash.com/documentation#error-messages
         */
        try {
            return app('unsplash')->download($args['id']);
        } catch (Exception $e) {
            if (!in_array($e->getCode(), [500, 503])) {
                captureException($e);
            }

            throw new UnexpectedHttpException();
        }
    }
}
