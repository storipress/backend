<?php

namespace App\GraphQL\Mutations\User;

use App\GraphQL\Mutations\Mutation;
use Illuminate\Contracts\Auth\Authenticatable;

final class UpdateUser extends Mutation
{
    /**
     * @param  array<string, string>  $args
     * @return Authenticatable|null
     */
    public function __invoke($_, array $args)
    {
        return auth()->user();
    }
}
