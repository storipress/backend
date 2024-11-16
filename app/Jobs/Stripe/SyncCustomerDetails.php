<?php

namespace App\Jobs\Stripe;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Cashier\PaymentMethod;
use Webmozart\Assert\Assert;

class SyncCustomerDetails implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected int $id)
    {
        //
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->id);

        Assert::isInstanceOf($user, User::class);

        $user->syncStripeCustomerDetails();

        $user->updateStripeCustomer([
            'metadata' => [
                'id' => $user->getKey(),
                'type' => 'user',
            ],
        ]);

        if (!$user->hasDefaultPaymentMethod()) {
            $user->updateDefaultPaymentMethodFromStripe();
        }

        if (!$user->hasDefaultPaymentMethod()) {
            $payment = $user->paymentMethods()->first();

            if ($payment instanceof PaymentMethod) {
                $user->updateDefaultPaymentMethod(
                    $payment->asStripePaymentMethod()->id,
                );
            }
        }
    }
}
