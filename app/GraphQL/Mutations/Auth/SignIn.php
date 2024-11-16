<?php

namespace App\GraphQL\Mutations\Auth;

use App\Enums\AccessToken\Type;
use App\Events\Auth\SignedIn;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Webmozart\Assert\Assert;

final class SignIn extends Auth
{
    /**
     * @param  array<string, string>  $args
     * @return array<int|string>
     */
    public function __invoke($_, array $args): array
    {
        $credentials = Arr::only($args, ['email', 'password']);

        try {
            /** @var User $user */
            $user = User::whereEmail($credentials['email'])->sole();
        } catch (ModelNotFoundException) {
            throw new InvalidCredentialsException();
        } catch (MultipleRecordsFoundException) {
            throw new InternalServerErrorHttpException();
        }

        Assert::isInstanceOf($user, User::class);

        if (!Hash::check($credentials['password'], $user->password)) {
            throw new InvalidCredentialsException();
        }

        $token = $user->accessTokens()->create([
            'name' => 'sign-in',
            'token' => AccessToken::token(Type::user()),
            'abilities' => '*',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addYears(5),
        ]);

        UserActivity::log(
            name: 'auth.sign_in',
            userId: $user->id,
        );

        SignedIn::dispatch($user->id);

        return $this->responseWithToken($token);
    }
}
