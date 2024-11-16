<?php

namespace App\Http\Controllers\Webhooks;

use App\Console\Schedules\Daily\GatherProphetMetrics;
use App\Http\Controllers\Controller;
use App\Models\EmailEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

class ProphetMailRepliedController extends Controller
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

        $event = EmailEvent::withoutEagerLoads()
            ->with(['email', 'email.tenant'])
            ->where('message_id', 'like', sprintf('%s-%%', $threadId))
            ->where('message_id', '!=', $messageId)
            ->where('record_type', '=', 'Delivery')
            ->first();

        if (!$event) {
            return response()->json(['ok' => false, 'extra' => 'event']);
        }

        $email = $event->email;

        if (!$email || !$email->tenant) {
            return response()->json(['ok' => false, 'extra' => 'email']);
        }

        $occurredAt = Carbon::parse($validated['created_at'])->setTimezone('UTC');

        EmailEvent::create([
            'message_id' => $messageId,
            'record_type' => 'Reply',
            'recipient' => $validated['to'],
            'from' => $validated['from'],
            'details' => $validated['body'],
            'occurred_at' => $occurredAt,
            'raw' => $request->getContent(),
        ]);

        $email->tenant->run(function () use ($threadId, $email, $occurredAt) {
            $email->subscriberEvents()->create([
                'subscriber_id' => $email->user_id,
                'name' => 'prophet.email.replied',
                'data' => [
                    'thread_id' => $threadId,
                    'subject' => $email->subject,
                ],
                'occurred_at' => $occurredAt,
            ]);
        });

        Artisan::queue(GatherProphetMetrics::class, [
            '--tenants' => [$email->tenant->id],
            '--date' => $occurredAt->toDateString(),
        ]);

        return response()->json(['ok' => true]);
    }
}
