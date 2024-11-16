<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Enums\AccessToken\Type;
use App\Exceptions\InvalidCredentialsException;
use App\Models\AccessToken;
use App\Models\Subscriber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Webmozart\Assert\Assert;

class SignInSubscriber
{
    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): string
    {
        try {
            /** @var string $token */
            $token = decrypt($args['token']);
        } catch (DecryptException) {
            throw new InvalidCredentialsException();
        }

        $id = Cache::pull($token);

        if (empty($id)) {
            throw new InvalidCredentialsException();
        }

        $subscriber = Subscriber::find($id);

        Assert::isInstanceOf($subscriber, Subscriber::class);

        if ($subscriber->verified_at === null) {
            $subscriber->update(['verified_at' => now()]);
        }

        $accessToken = $subscriber->accessTokens()->create([
            'name' => 'sign-in',
            'token' => AccessToken::token(Type::subscriber()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        Assert::isInstanceOf($accessToken, AccessToken::class);

        return $accessToken->token;
    }
}
