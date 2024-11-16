<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\BadRequestHttpException;
use App\Mail\SubscriberSignInMail;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RequestSignInSubscriber
{
    use Auth;

    /**
     * @param  array<string, string>  $args
     */
    public function __invoke($_, array $args): bool
    {
        $email = $args['email'];

        /** @var Subscriber|null $subscriber */
        $subscriber = Subscriber::whereEmail($email)->first();

        if ($subscriber === null) {
            throw new BadRequestHttpException();
        }

        $tenantSubscriber = TenantSubscriber::firstOrCreate(
            ['id' => $subscriber->getKey()],
            ['signed_up_source' => $this->guessSource($args['referer'])],
        );

        if ($tenantSubscriber->wasRecentlyCreated) {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $subscriber->tenants()->attach($tenant);
        }

        $key = sprintf('subscriber-sign-in-%s', Str::uuid()->toString());

        Cache::put($key, $subscriber->getKey(), now()->addDays());

        Mail::to($email)->send(
            new SubscriberSignInMail(
                $subscriber->full_name ?: 'there',
                $this->link($args['from'], $key, 'sign-in'),
            ),
        );

        return true;
    }
}
