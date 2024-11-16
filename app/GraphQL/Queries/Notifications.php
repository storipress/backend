<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

final readonly class Notifications
{
    /**
     * @param  array{}  $args
     * @return Collection<int, DatabaseNotification>
     */
    public function __invoke(null $_, array $args): Collection
    {
        $empty = new Collection();

        $user = auth()->user();

        if (! ($user instanceof User)) {
            return $empty; // @phpstan-ignore-line
        }

        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            return $empty; // @phpstan-ignore-line
        }

        return $user
            ->notifications()
            ->whereJsonContains('data->tenant_id', $tenant->id)
            ->take(25)
            ->get();
    }
}
