<?php

namespace App\GraphQL\Mutations\Site;

use App\Enums\AccessToken\Type;
use App\Exceptions\AccessDeniedHttpException;
use App\Models\AccessToken;
use App\Models\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Webmozart\Assert\Assert;

final class GenerateNewstandKey
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): string
    {
        $auth = auth()->user();

        if (!($auth instanceof User)) {
            throw new AccessDeniedHttpException();
        }

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $user = TenantUser::find($auth->id);

        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        if (!in_array($user->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException();
        }

        $tenant->accessToken?->update(['expires_at' => now()]);

        $token = $tenant->accessToken()->create([
            'name' => 'newstand-api',
            'token' => AccessToken::token(Type::tenant()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        Assert::isInstanceOf($token, AccessToken::class);

        return $token->token;
    }
}
