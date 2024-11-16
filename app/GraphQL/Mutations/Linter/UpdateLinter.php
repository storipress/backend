<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Linter;

use App\Exceptions\ErrorCode;
use App\Exceptions\ErrorException;
use App\Models\Tenants\Linter;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;

final readonly class UpdateLinter
{
    /**
     * @param  array{
     *     id: string,
     *     title?: string,
     *     description?: string,
     *     prompt?: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): Linter
    {
        $linter = Linter::find($args['id']);

        if (! ($linter instanceof Linter)) {
            throw new ErrorException(ErrorCode::NOT_FOUND);
        }

        $attributes = Arr::only($args, [
            'title', 'description', 'prompt',
        ]);

        if (empty($attributes)) {
            return $linter;
        }

        $linter->update($attributes);

        UserActivity::log(
            name: 'linter.update',
            subject: $linter,
        );

        return $linter->refresh();
    }
}
