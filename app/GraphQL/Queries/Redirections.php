<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Tenants\Redirection;
use Illuminate\Database\Eloquent\Collection;

final readonly class Redirections
{
    /**
     * @param  array{}  $args
     * @return Collection<int, Redirection>
     */
    public function __invoke(null $_, array $args): Collection
    {
        return Redirection::get();
    }
}
