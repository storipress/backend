<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Exceptions\ErrorCode;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * @phpstan-type TValidation array{
 *     email: string,
 *     verdict: 'Valid'|'Risky'|'Invalid',
 *     score: double,
 *     local: string,
 *     host: string,
 *     checks: mixed,
 *     ip_address: string,
 * }
 */
class CreateSubscriberController extends ZapierController
{
    protected string $topic = 'subscriber.create';

    /**
     * redirect to authorize url
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = auth()->user();

        if (!$tenant instanceof Tenant) {
            // unauthorized
            return $this->failed(ErrorCode::ZAPIER_MISSING_CLIENT, 401);
        }

        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'email' => 'required|email:rfc,strict,dns,spoof',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'newsletter' => 'nullable|boolean',
            'verified_at' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_PAYLOAD, 400);
        }

        if ($request->input('topic') !== $this->topic) {
            return $this->failed(ErrorCode::ZAPIER_INVALID_TOPIC, 400);
        }

        /** @var string $email */
        $email = $request->input('email');

        /** @var string|null $firstName */
        $firstName = $request->input('first_name');

        /** @var string|null $lastName */
        $lastName = $request->input('last_name');

        /** @var bool|null $newsletter */
        $newsletter = $request->input('newsletter');

        if ($verifiedAt = $request->input('verified_at')) {
            /** @var string $verifiedAt */
            if (is_numeric($verifiedAt)) {
                $verifiedAt = '@' . $verifiedAt;
            }

            $verifiedAt = Carbon::parse($verifiedAt);
        }

        /** @var Carbon|null $verifiedAt */
        $subscriber = Subscriber::firstOrCreate([
            'email' => Str::lower($email),
        ], [
            'first_name' => $firstName ? trim($firstName) : null,
            'last_name' => $lastName ? trim($lastName) : null,
            'verified_at' => $verifiedAt?->timestamp,
        ]);

        if (empty($subscriber->validation)) {
            $validation = $this->validateEmail($subscriber->email);

            $subscriber->update([
                'bounced' => ($validation['verdict'] ?? '') === 'Invalid',
                'validation' => $validation,
            ]);
        }

        $subscriber->tenants()->syncWithoutDetaching($tenant);

        $route = Route::currentRouteName() ?: '';

        $source = Str::contains($route, 'pabbly-connect') ? 'pabbly-connect' : 'zapier';

        $tenant->run(function () use ($subscriber, $source, $newsletter) {
            TenantSubscriber::firstOrCreate([
                'id' => $subscriber->id,
            ], [
                'signed_up_source' => $source,
                'newsletter' => $newsletter ?: false,
            ]);

            // segment track
        });

        return response()->json($subscriber->toWebhookArray());
    }

    /**
     * @return TValidation|null
     */
    protected function validateEmail(string $email): ?array
    {
        try {
            /** @var TValidation $result */
            $result = app('sendgrid')
                ->post('/validations/email', ['email' => $email])
                ->json('result');

            return $result;
        } catch (Throwable) {
            return null;
        }
    }
}
