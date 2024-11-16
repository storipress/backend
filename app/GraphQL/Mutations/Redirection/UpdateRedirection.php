<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Redirection;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\Redirection;

final readonly class UpdateRedirection
{
    /**
     * @param  array{
     *     id: string,
     *     path: string,
     *     target: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): Redirection
    {
        $redirection = Redirection::find($args['id']);

        if (! ($redirection instanceof Redirection)) {
            throw new HttpException(ErrorCode::NOT_FOUND);
        }

        $redirection->update([
            'path' => $args['path'],
            'target' => $args['target'],
        ]);

        return $redirection;
    }
}
