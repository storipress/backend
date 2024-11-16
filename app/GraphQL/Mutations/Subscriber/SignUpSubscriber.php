<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Enums\AccessToken\Type;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\QuotaExceededHttpException;
use App\Mail\SubscriberEmailVerifyMail;
use App\Models\AccessToken;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class SignUpSubscriber
{
    use Auth;

    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): string
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new BadRequestHttpException();
        }

        $plan = 'free';

        if (($subscription = $tenant->owner->subscription()) !== null) {
            $plan = Str::before($subscription->stripe_price ?: '', '-');
        }

        $key = sprintf('billing.quota.subscribers.%s', $plan);

        $quota = config($key);

        if (!is_int($quota)) {
            $quota = 200;
        }

        if (TenantSubscriber::count() > $quota) {
            // throw new QuotaExceededHttpException();
        }

        $subscriber = Subscriber::firstOrCreate([
            'email' => Str::lower($args['email']),
        ]);

        if (!$subscriber->wasRecentlyCreated) {
            throw new BadRequestHttpException();
        }

        $subscriber->tenants()->attach(tenant());

        Mail::to($subscriber->email)->send(
            new SubscriberEmailVerifyMail(
                'there',
                $this->link($args['from'], $subscriber->email, 'verify-email'),
            ),
        );

        $id = $subscriber->getKey();

        TenantSubscriber::create([
            'id' => $id,
            'signed_up_source' => $this->guessSource($args['referer']),
        ]);

        $accessToken = $subscriber->accessTokens()->create([
            'name' => 'sign-up',
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
