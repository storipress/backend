<?php

namespace App\Listeners\Partners\Postmark\WebhookReceiving;

use App\Enums\Email\EmailUserType;
use App\Events\Partners\Postmark\WebhookReceived;
use App\Events\Partners\Postmark\WebhookReceiving;
use App\Models\Email;
use App\Models\EmailEvent;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class SaveEventToDatabase implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The time (seconds) before the job should be processed.
     */
    public int $delay = 60;

    protected WebhookReceiving $event;

    /**
     * Handle the event.
     */
    public function handle(WebhookReceiving $event): void
    {
        $this->event = $event;

        $types = ['delivery', 'bounce', 'open', 'click'];

        $type = $event->inputs['RecordType'] ?? '';

        if (! is_not_empty_string($type)) {
            return;
        }

        $method = Str::lower($type);

        if (! in_array($method, $types, true)) {
            return;
        }

        $this->{$method}();
    }

    /**
     * Record email delivery event.
     *
     * Reference: https://postmarkapp.com/developer/webhooks/delivery-webhook
     */
    protected function delivery(): void
    {
        $this->saveToDatabase([
            'message_id' => 'MessageID',
            'RecordType',
            'Recipient',
            'Details',
            'Tag',
            'Metadata',
        ]);

        $email = Email::where('message_id', '=', $this->event->inputs['MessageID'])->first();

        if (! ($email instanceof Email)) {
            return;
        }

        $token = EmailUserType::user()->is($email->user_type)
            ? config('services.postmark.app_server_token')
            : config('services.postmark.subscriptions_server_token');

        $subject = app('http')
            ->retry(3, 1000)
            ->withHeaders(['X-Postmark-Server-Token' => $token])
            ->get(sprintf('https://api.postmarkapp.com/messages/outbound/%s/details', $email->message_id))
            ->json('Subject');

        if (! empty($subject)) {
            $email->update(['subject' => $subject]);
        }
    }

    /**
     * Record email bounce event.
     *
     * Reference: https://postmarkapp.com/developer/webhooks/bounce-webhook
     * Reference: https://postmarkapp.com/developer/api/bounce-api#bounce-types
     */
    protected function bounce(): void
    {
        $event = $this->saveToDatabase([
            'message_id' => 'MessageID',
            'RecordType',
            'recipient' => 'Email',
            'From',
            'Description',
            'Details',
            'Tag',
            'Metadata',
            'bounce_id' => 'ID',
            'bounce_code' => 'TypeCode',
            'bounce_content' => 'Content',
        ]);

        Subscriber::where('email', '=', $event->recipient)
            ->update(['bounced' => true]);
    }

    /**
     * Record email open event.
     *
     * Reference: https://postmarkapp.com/developer/webhooks/open-tracking-webhook
     */
    protected function open(): void
    {
        $this->saveToDatabase([
            'message_id' => 'MessageID',
            'RecordType',
            'Recipient',
            'Details',
            'Tag',
            'Metadata',
            'ip' => 'Geo.IP',
            'UserAgent',
            'FirstOpen',
        ]);
    }

    /**
     * Record email click event.
     *
     * Reference: https://postmarkapp.com/developer/webhooks/click-webhook
     */
    protected function click(): void
    {
        $this->saveToDatabase([
            'message_id' => 'MessageID',
            'RecordType',
            'Recipient',
            'Details',
            'Tag',
            'Metadata',
            'ip' => 'Geo.IP',
            'UserAgent',
            'link' => 'OriginalLink',
            'ClickLocation',
        ]);
    }

    /**
     * Save target fields to database.
     *
     * @param  string[]  $fields
     */
    protected function saveToDatabase(array $fields): EmailEvent
    {
        $dates = Arr::only($this->event->inputs, [
            'DeliveredAt',
            'BouncedAt',
            'ReceivedAt',
        ]);

        $date = Arr::first(array_filter($dates));

        Assert::stringNotEmpty($date);

        $attributes = [
            'occurred_at' => Carbon::parse($date),
            'raw' => $this->event->body,
        ];

        foreach ($fields as $origin => $field) {
            $key = is_int($origin) ? Str::snake($field) : $origin;

            $attributes[$key] = $this->event->inputs[$field] ?? null;
        }

        $event = EmailEvent::create($attributes);

        WebhookReceived::dispatch($event->id);

        return $event;
    }
}
