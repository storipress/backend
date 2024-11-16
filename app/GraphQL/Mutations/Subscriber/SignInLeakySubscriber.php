<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Enums\AccessToken\Type;
use App\Exceptions\BadRequestHttpException;
use App\Models\AccessToken;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Support\Str;

class SignInLeakySubscriber
{
    use Auth;

    /**
     * @param  array{
     *     email: string,
     * }  $args
     */
    public function __invoke($_, array $args): string
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new BadRequestHttpException();
        }

        $subscriber = Subscriber::firstOrCreate([
            'email' => Str::lower($args['email']),
        ]);

        if (! ($subscriber instanceof Subscriber)) {
            throw new BadRequestHttpException();
        }

        $tSubscriber = TenantSubscriber::firstOrCreate([
            'id' => $subscriber->id,
        ], [
            'signed_up_source' => 'Unknown',
        ]);

        if ($tSubscriber->wasRecentlyCreated) {
            $subscriber->tenants()->sync($tenant, false);
        }

        $accessToken = $subscriber->accessTokens()->create([
            'name' => $subscriber->wasRecentlyCreated ? 'sign-up' : 'sign-in',
            'token' => AccessToken::token(Type::subscriber()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        if (! ($accessToken instanceof AccessToken)) {
            throw new BadRequestHttpException();
        }

        return $accessToken->token;
    }
}
