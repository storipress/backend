<?php

namespace App\Listeners\StripeWebhookReceived;

use App\Enums\Credit\State as CreditState;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Sentry\State\Scope;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice as StripeInvoice;
use Throwable;

use function Sentry\captureException;
use function Sentry\configureScope;

class HandleInvoiceCreated implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The time (seconds) before the job should be processed.
     */
    public int $delay = 15;

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(WebhookReceived $event): bool
    {
        return $event->payload['type'] === 'invoice.created';
    }

    /**
     * Handle the event.
     *
     *
     * @throws ApiErrorException
     * @throws Throwable
     *
     * @link https://stripe.com/docs/webhooks
     * @link https://stripe.com/docs/billing/subscriptions/coupons
     * @link https://stripe.com/docs/api/events/types
     * @link https://stripe.com/docs/api/invoices
     * @link https://stripe.com/docs/api/invoiceitems
     * @link https://stripe.com/docs/api/coupons
     */
    public function handle(WebhookReceived $event): void
    {
        configureScope(function (Scope $scope) use ($event) {
            $scope->setContext('stripe-event', $event->payload);
        });

        $customerId = Arr::get($event->payload, 'data.object.customer');

        if (! is_not_empty_string($customerId)) {
            return;
        }

        $invoiceId = Arr::get($event->payload, 'data.object.id');

        if (! is_not_empty_string($invoiceId)) {
            return;
        }

        $user = Cashier::findBillable($customerId);

        // @phpstan-ignore-next-line
        if (! ($user instanceof User)) {
            return;
        }

        // @phpstan-ignore-next-line
        $credits = $user->credits()
            ->where('state', '=', CreditState::available())
            ->get();

        if ($credits->isEmpty()) {
            return;
        }

        $invoice = $user->findInvoice($invoiceId)?->asStripeInvoice();

        if ($invoice === null) {
            return;
        }

        if ($invoice->status !== StripeInvoice::STATUS_DRAFT) {
            return; // non-draft invoice is not updatable
        }

        if ($invoice->total_excluding_tax <= 0) {
            return;
        }

        $stripe = Cashier::stripe();

        $now = now();

        $remaining = $invoice->total_excluding_tax;

        DB::beginTransaction();

        try {
            while ($remaining > 0 && $credits->isNotEmpty()) {
                $credit = $credits->shift();

                if ($remaining >= $credit->amount) {
                    $remaining -= $credit->amount;
                } else {
                    $user->credits()->create([
                        'state' => CreditState::available(),
                        'amount' => $credit->amount - $remaining,
                        'earned_from' => $credit->earned_from,
                        'data' => $credit->data,
                    ]);

                    $remaining = 0;
                }

                $credit->update([
                    'state' => CreditState::used(),
                    'invoice_id' => $invoiceId,
                    'used_at' => $now,
                ]);
            }

            $coupon = $stripe->coupons->create([
                'name' => 'Credit',
                'amount_off' => $invoice->total_excluding_tax - $remaining,
                'currency' => 'USD',
                'duration' => 'once',
                'max_redemptions' => 1,
                'metadata' => [
                    'user_id' => $user->id,
                    'invoice_id' => $invoiceId,
                ],
            ]);

            $stripe->invoices->update($invoiceId, [
                'discounts' => [['coupon' => $coupon->id]],
            ]);

            DB::commit();
        } catch (ApiErrorException $e) {
            DB::rollBack();

            if (isset($coupon)) {
                // cleanup coupon when there is something wrong
                $stripe->coupons->delete($coupon->id);
            }

            captureException($e);
        }
    }
}
