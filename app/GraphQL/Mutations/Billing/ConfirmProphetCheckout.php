<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Billing;

use App\Mail\UserProphetWelcomeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Cashier;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

final readonly class ConfirmProphetCheckout
{
    /**
     * @param  array{
     *     checkout_id: string,
     * }  $args
     * @return array{
     *     exists: bool,
     *     email: string,
     *     first_name: string|null,
     *     last_name: string|null,
     * }|null
     */
    public function __invoke(null $_, array $args): ?array
    {
        $checkoutId = $args['checkout_id'];

        if (empty($checkoutId)) {
            return null;
        }

        $used = DB::table('subscriptions')
            ->where('stripe_id', '=', $checkoutId)
            ->exists();

        if ($used) {
            return null;
        }

        try {
            $checkout = Cashier::stripe()
                ->checkout
                ->sessions
                ->retrieve($checkoutId, ['expand' => ['customer']]);
        } catch (ApiErrorException) {
            return null;
        }

        if (! ($checkout->customer instanceof Customer)) {
            return null;
        }

        $email = $checkout->customer->email;

        if ($email === null) {
            return null;
        }

        $names = explode(
            ' ',
            $checkout->customer->name ?: '',
            2,
        );

        $exists = DB::table('users')
            ->where('email', '=', $email)
            ->exists();

        $key = sprintf('prophet-welcome-%s', $checkoutId);

        if (Cache::add($key, true, now()->addWeeks(2))) {
            Mail::to($email)->send(
                new UserProphetWelcomeMail(
                    $names[0] ?: 'there',
                ),
            );
        }

        return [
            'exists' => $exists,
            'email' => $email,
            'first_name' => $names[0] ?: null,
            'last_name' => ($names[1] ?? null) ?: null,
        ];
    }
}
