<?php

namespace App\GraphQL\Queries;

use App\Exceptions\BadRequestHttpException;
use App\Models\Subscriber;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Webmozart\Assert\Assert;

class SubscriberProfile
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): TenantSubscriber
    {
        $id = auth()->id();

        Assert::notNull($id);

        $subscriber = TenantSubscriber::find($id);

        if ($subscriber !== null) {
            return $subscriber;
        }

        $base = Subscriber::find($id);

        if ($base === null) {
            throw new BadRequestHttpException();
        }

        $subscriber = TenantSubscriber::firstOrCreate(
            ['id' => $id],
            ['signed_up_source' => 'Direct'],
        );

        $base->tenants()->attach(tenant());

        return $subscriber->refresh();
    }
}
