<?php

namespace App\Listeners\Partners\Shopify\WebhookReceived;

use App\Models\Subscriber;
use App\Models\Tenants\Integration;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use App\Resources\Partners\Shopify\Customer;
use Illuminate\Support\Arr;

trait WebhookHelper
{
    protected ?Subscriber $subscriber = null;

    protected function isCustomerSyncingEnabled(): bool
    {
        $shopify = Integration::activated()
            ->find('shopify');

        if ($shopify === null) {
            return false;
        }

        if (! Arr::get($shopify, 'data.sync_customers')) {
            return false;
        }

        return true;
    }

    protected function updateOrCreateSubscriber(Customer $customer): Subscriber
    {
        if ($this->subscriber !== null) {
            return $this->subscriber;
        }

        $attributes = [
            'email' => $customer->email,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
        ];

        $this->subscriber = Subscriber::where('email', '=', $customer->email)->first();

        if ($this->subscriber === null) {
            return $this->subscriber = Subscriber::create(
                array_merge($attributes, [
                    'verified_at' => $customer->verifiedEmail ? now() : null,
                ]),
            );
        }

        $this->subscriber->fill($attributes);

        if ($customer->verifiedEmail && ! $this->subscriber->verified) {
            $this->subscriber->verified_at = now();
        }

        if ($this->subscriber->isDirty(array_merge(array_keys($attributes), ['verified_at']))) {
            $this->subscriber->save();
        }

        return $this->subscriber;
    }

    protected function updateOrCreateTenantSubscriber(int $id, Customer $customer): TenantSubscriber
    {
        $attributes = [
            'shopify_id' => $customer->id,
            'newsletter' => $customer->acceptsMarketing,
        ];

        $subscriber = TenantSubscriber::find($id);

        if ($subscriber === null) {
            return TenantSubscriber::create(
                array_merge($attributes, [
                    'id' => $id,
                    'signed_up_source' => 'shopify',
                ]),
            );
        }

        $subscriber->update($attributes);

        return $subscriber;
    }
}
