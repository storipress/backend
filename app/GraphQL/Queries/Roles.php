<?php

namespace App\GraphQL\Queries;

class Roles
{
    /**
     * @param  array{}  $args
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     title: string,
     *     level: int,
     * }>
     */
    public function __invoke($_, array $args): array
    {
        return roles();
    }
}
