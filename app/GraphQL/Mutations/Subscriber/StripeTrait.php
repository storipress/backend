<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\PaymentMethod;
use Stripe\Exception\ApiErrorException;
use Webmozart\Assert\Assert;

trait StripeTrait
{
    /**
     * @throws ApiErrorException
     */
    protected function ensureCustomerExists(TenantSubscriber $subscriber): void
    {
        if ($subscriber->hasStripeId()) {
            return;
        }

        $subscriber->createOrGetStripeCustomer([
            'metadata' => [
                'id' => $subscriber->getKey(),
                'type' => 'subscriber',
            ],
        ]);

        $parent = $subscriber->parent;

        Assert::isInstanceOf($parent, Subscriber::class);

        if (!$parent->hasDefaultPaymentMethod()) {
            return;
        }

        $this->syncPaymentMethodToTenants($parent, null, tenant()); // @phpstan-ignore-line
    }

    /**
     * @throws ApiErrorException
     */
    protected function syncPaymentMethodToTenants(
        Subscriber $subscriber,
        ?PaymentMethod $payment = null,
        ?Tenant $tenant = null,
    ): void {
        $payment = $payment ?: $subscriber->defaultPaymentMethod();

        Assert::isInstanceOf($payment, PaymentMethod::class);

        $sourcePaymentMethodId = $payment->asStripePaymentMethod()->id;

        tenancy()->runForMultiple(
            $tenant ? [$tenant] : $subscriber->tenants, // @phpstan-ignore-line
            function () use ($subscriber, $sourcePaymentMethodId) {
                $tenantSubscriber = TenantSubscriber::find($subscriber->id);

                if (!$tenantSubscriber || !$tenantSubscriber->hasStripeId()) {
                    return;
                }

                $stripe = $tenantSubscriber->stripe();

                if ($stripe === null) {
                    return;
                }

                // https://stripe.com/docs/connect/cloning-customers-across-accounts
                $paymentMethod = $stripe->paymentMethods->create([
                    'customer' => $subscriber->stripe_id,
                    'payment_method' => $sourcePaymentMethodId,
                ]);

                $tenantSubscriber->addPaymentMethod($paymentMethod);

                $tenantSubscriber->updateDefaultPaymentMethod($paymentMethod);
            },
        );
    }

    /**
     * @template T of bool|void
     *
     * @param  callable(): T  $callable
     * @return T
     */
    protected function wrapCashierConfigForSubscriber(callable $callable): mixed
    {
        try {
            $origin = Cashier::$customerModel;

            Cashier::$calculatesTaxes = false;

            Cashier::$customerModel = 'App\\Models\\Tenants\\Subscriber';

            return $callable();
        } finally {
            Cashier::$customerModel = $origin;

            Cashier::$calculatesTaxes = true;
        }
    }
}
