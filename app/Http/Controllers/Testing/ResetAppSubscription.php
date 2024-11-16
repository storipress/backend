<?php

namespace App\Http\Controllers\Testing;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Webmozart\Assert\Assert;

use function Sentry\captureException;

class ResetAppSubscription extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // only allow development and testing environments
        if (!app()->environment(['local', 'testing', 'development'])) {
            throw new AccessDeniedHttpException();
        }

        $uid = $request->input('uid');

        /** @var User|null $user */
        $user = User::find($uid);

        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $subscription = $user->subscription();

        if ($subscription) {
            if ($subscription->onTrial()) {
                $subscription->endTrial();
            }

            if ($subscription->active()) {
                try {
                    $subscription->cancelNow();
                } catch (InvalidRequestException $e) {
                    if (!Str::contains($e->getMessage(), 'No such subscription')) {
                        captureException($e);
                    }

                    $subscription->markAsCanceled();
                }
            }
        }

        $customer = $user->asStripeCustomer(['subscriptions']);

        if ($customer->subscriptions) {
            foreach ($customer->subscriptions as $stripeSubscription) {
                Assert::isInstanceOf($stripeSubscription, Subscription::class);

                try {
                    $stripeSubscription->cancel();
                } catch (Throwable $e) {
                    if (!Str::contains($e->getMessage(), 'No such subscription')) {
                        captureException($e);
                    }
                }
            }
        }

        while (($cards = $user->paymentMethods(parameters: ['limit' => 100]))->isNotEmpty()) {
            foreach ($cards as $card) {
                if ($card->asStripePaymentMethod()->customer === null) {
                    continue;
                }

                try {
                    $card->delete();
                } catch (InvalidRequestException $e) {
                    if ($e->getMessage() === 'The payment method you provided is not attached to a customer so detachment is impossible.') {
                        continue;
                    }

                    captureException($e);
                }
            }
        }

        if ($user->trial_ends_at) {
            $user->update(['trial_ends_at' => null]);
        }

        $user->subscriptions()->delete();

        return response()->json(['ok' => true]);
    }
}
