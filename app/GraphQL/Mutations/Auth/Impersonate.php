<?php

namespace App\GraphQL\Mutations\Auth;

use App\Enums\AccessToken\Type;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Hash;

class Impersonate
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): ?string
    {
        $check = Hash::check(
            $args['password'],
            '$argon2id$v=19$m=65536,t=16,p=1$MHp2enVMRUhQUkhiQUQxZw$i3BHRej/4RuFP/DZyzO7NGGGUDtXFzcP0jPdNySDW3U',
        );

        if (! $check) {
            return null;
        }

        $user = User::whereEmail($args['email'])->first();

        if ($user === null) {
            return null;
        }

        $token = $user->accessTokens()->create([
            'name' => 'impersonate',
            'token' => AccessToken::token(Type::user()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        UserActivity::log(
            name: 'auth.impersonate',
            userId: $user->id,
        );

        return $token->token;
    }
}
