<?php

namespace App\GraphQL\Queries;

use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Webmozart\Assert\Assert;

final class Users
{
    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, TenantUser>
     */
    public function __invoke($_, array $args): Collection
    {
        $attributes = [
            'email',
            'verified',
            'first_name',
            'last_name',
            'slug',
            'gender',
            'birthday',
            'phone_number',
            'location',
            'bio',
            'website',
            'socials',
            'avatar',
        ];

        $users = TenantUser::all();

        $ids = $users->pluck('id')->toArray();

        $bases = User::whereIn('id', $ids)->get();

        foreach ($users as $user) {
            $base = $bases->firstWhere('id', '=', $user->getKey());

            Assert::isInstanceOf($base, User::class);

            foreach ($attributes as $key) {
                $user->setAttribute($key, $base->getAttribute($key));
            }
        }

        return $users;
    }
}
