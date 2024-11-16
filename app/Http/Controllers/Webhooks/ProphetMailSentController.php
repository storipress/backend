<?php

namespace App\Http\Controllers\Webhooks;

use App\Console\Schedules\Daily\GatherProphetMetrics;
use App\Enums\Email\EmailUserType;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\EmailEvent;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type StoripressData array{
 *     tenant_id: string,
 *     subscriber_id: int,
 * }
 */
class ProphetMailSentController extends Controller
{
    /**
     * @var array<string, string>
     */
    public array $rules = [
        'id' => 'required|string',
        'threadId' => 'required|string',
        'from' => 'required|string',
        'to' => 'required|string',
        'subject' => 'required|string',
        'body' => 'required|string',
        'created_at' => 'required|string',
    ];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== 'xaat-7a848191-439a-4fe5-9850-91e9b99d7173') {
            return response()->json(['ok' => false, 'extra' => 'authentication']);
        }

        try {
            $validated = $request->validate($this->rules);
        } catch (ValidationException) {
            return response()->json(['ok' => false, 'extra' => 'validation']);
        }

        $threadId = $validated['threadId'];

        $messageId = sprintf('%s-%s', $threadId, $validated['id']);

        if (! is_not_empty_string($request->header('storipress'))) {
            return response()->json(['ok' => false, 'extra' => 'storipress']);
        }

        try {
            /** @var StoripressData $storipress */
            $storipress = decrypt($request->header('storipress'));
        } catch (DecryptException) {
            return response()->json(['ok' => false, 'extra' => 'storipress']);
        }

        $occurredAt = Carbon::parse($validated['created_at'])->setTimezone('UTC');

        $email = Email::create([
            'tenant_id' => $storipress['tenant_id'],
            'user_id' => $storipress['subscriber_id'],
            'user_type' => EmailUserType::subscriber(),
            'message_id' => $messageId,
            'template_id' => 0,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'subject' => $validated['subject'],
            'content' => $validated['body'],
            'data' => $request->all(),
        ]);

        EmailEvent::create([
            'message_id' => $messageId,
            'record_type' => 'Delivery',
            'recipient' => $validated['to'],
            'from' => $validated['from'],
            'occurred_at' => $occurredAt,
            'raw' => $request->getContent(),
        ]);

        $email->tenant?->run(function () use ($threadId, $email, $occurredAt) {
            $email->subscriberEvents()->create([
                'subscriber_id' => $email->user_id,
                'name' => 'prophet.email.sent',
                'data' => [
                    'thread_id' => $threadId,
                    'subject' => $email->subject,
                ],
                'occurred_at' => $occurredAt,
            ]);
        });

        Artisan::queue(GatherProphetMetrics::class, [
            '--tenants' => [$storipress['tenant_id']],
            '--date' => $occurredAt->toDateString(),
        ]);

        return response()->json(['ok' => true]);
    }
}
