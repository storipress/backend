<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Linter;

use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Linter;
use App\Models\Tenants\UserActivity;

final readonly class DeleteLinter
{
    /**
     * @param  array{
     *     id: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): Linter
    {
        $linter = Linter::find($args['id']);

        if (!($linter instanceof Linter)) {
            throw new ErrorException(ErrorCode::NOT_FOUND);
        }

        $linter->delete();

        UserActivity::log(
            name: 'linter.delete',
            subject: $linter,
        );

        return $linter;
    }
}
