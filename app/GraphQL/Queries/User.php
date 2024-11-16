<?php

namespace App\GraphQL\Queries;

use App\Exceptions\BadRequestHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\User as TenantUser;
use App\Models\User as BaseUser;
use Illuminate\Database\Eloquent\Builder;
use Webmozart\Assert\Assert;

final class User
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): TenantUser
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

        if (count($args) === 0) {
            throw new BadRequestHttpException();
        }

        $base = (new BaseUser())
            ->when(isset($args['id']), fn (Builder $query) => $query->where('id', $args['id']))
            ->when(isset($args['slug']), fn (Builder $query) => $query->where('slug', $args['slug']))
            ->first();

        Assert::nullOrIsInstanceOf($base, BaseUser::class);

        // user does not exists
        if ($base === null) {
            throw new NotFoundHttpException();
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::where('id', '=', $base->getKey())->first();

        // user is not belong to the tenant
        if ($user === null) {
            throw new NotFoundHttpException();
        }

        foreach ($attributes as $key) {
            $user->setAttribute($key, $base->getAttribute($key));
        }

        return $user;
    }
}
