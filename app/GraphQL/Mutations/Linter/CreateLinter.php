<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Linter;

use App\Models\Tenants\Linter;
use App\Models\Tenants\UserActivity;

final readonly class CreateLinter
{
    /**
     * @param  array{
     *     title: string,
     *     description?: string,
     *     prompt: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): Linter
    {
        if (empty($args['description'])) {
            $args['description'] = '';
        }

        $linter = Linter::create($args);

        UserActivity::log(
            name: 'linter.create',
            subject: $linter,
        );

        return $linter->refresh();
    }
}
