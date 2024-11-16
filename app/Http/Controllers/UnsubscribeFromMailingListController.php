<?php

namespace App\Http\Controllers;

use App\Enums\Email\EmailUserType;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnsubscribeFromMailingListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $payload): Response
    {
        if (! $request->isMethod('POST') && ! $request->has('confirm')) {
            $url = $request->fullUrlWithQuery(['confirm' => 1]);

            return response(
                sprintf('<a href="%s">Confirm Unsubscribe</a>', $url),
            );
        }

        $response = response(
            'You successfully unsubscribed from the mailing list.',
        );

        try {
            /**
             * @var array{
             *     user_type: int,
             *     user_id: int,
             *     tenant: string,
             * } $data
             */
            $data = decrypt($payload);
        } catch (DecryptException) {
            return $response;
        }

        if (EmailUserType::user()->is($data['user_type'])) {
            $this->user($data);
        } elseif (EmailUserType::subscriber()->is($data['user_type'])) {
            $this->subscriber($data);
        }

        return $response;
    }

    /**
     * @param  array{
     *     tenant: string,
     *     user_id: int,
     * }  $data
     */
    protected function user(array $data): void
    {
        //
    }

    /**
     * @param  array{
     *     tenant: string,
     *     user_id: int,
     * }  $data
     */
    protected function subscriber(array $data): void
    {
        $tenant = Tenant::find($data['tenant']);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($data) {
            Subscriber::where('id', $data['user_id'])->update([
                'newsletter' => false,
            ]);
        });
    }
}
