<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Redirection;

use App\Models\Tenants\Redirection;

final readonly class CreateRedirection
{
    /**
     * @param  array{
     *     path: string,
     *     target: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): Redirection
    {
        $redirection = Redirection::withTrashed()
            ->updateOrCreate([
                'path' => $args['path'],
            ], [
                'target' => $args['target'],
                'deleted_at' => null,
            ]);

        return $redirection;
    }
}
