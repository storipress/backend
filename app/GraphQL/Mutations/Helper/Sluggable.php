<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Helper;

use App\Sluggable as SluggableHelper;

final readonly class Sluggable
{
    /**
     * @param  array{
     *     value: string,
     * }  $args
     */
    public function __invoke(null $_, array $args): string
    {
        if (empty($args['value'])) {
            return '';
        }

        return SluggableHelper::slug($args['value']);
    }
}
