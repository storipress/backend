<?php

namespace App\Listeners\Partners\Postmark\WebhookReceived;

use App\Console\Commands\Subscriber\GatherDailyMetrics;
use App\Enums\Email\EmailUserType;
use App\Events\Partners\Postmark\WebhookReceived;
use App\Models\Email;
use App\Models\EmailEvent;
use App\Models\Tenants\Analysis;
use App\Models\Tenants\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Sentry\State\Scope;

use function Sentry\captureException;
use function Sentry\withScope;

class TransformIntoSubscriberEvent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $payload): void
    {
        $event = EmailEvent::find($payload->eventId);

        if (! ($event instanceof EmailEvent)) {
            return;
        }

        if ($event->event_name === null) {
            return;
        }

        $email = Email::with('tenant')
            ->where('message_id', '=', $event->message_id)
            ->first();

        if (! ($email instanceof Email)) {
            return;
        }

        if ($email->tenant === null) {
            return;
        }

        if (EmailUserType::subscriber()->isNot($email->user_type)) {
            return;
        }

        if ($email->user_id === 0) {
            withScope(function (Scope $scope) use ($email, $event): void {
                $scope->setContext('email', $email->toArray());

                $scope->setContext('event', $event->toArray());

                captureException(new RuntimeException('Email user_id must not be 0.'));
            });

            return;
        }

        $email->tenant->run(function () use ($event, $email) {
            if (! Subscriber::where('id', '=', $email->user_id)->exists()) {
                return;
            }

            $email->subscriberEvents()->create([
                'subscriber_id' => $email->user_id,
                'name' => $event->event_name,
                'data' => $event->toData() ?: null,
                'occurred_at' => $event->occurred_at,
            ]);

            $this->updateAnalyses($event);
        });
    }

    /**
     * Update analyses data.
     */
    protected function updateAnalyses(EmailEvent $event): void
    {
        $mapping = [
            'email.received' => 'email_sends',
            'email.opened' => 'email_opens',
            'email.link_clicked' => 'email_clicks',
        ];

        if (! isset($mapping[$event->event_name])) {
            return;
        }

        $column = $mapping[$event->event_name];

        $date = $event->occurred_at;

        $monthly = Analysis::firstOrCreate([
            'year' => $date->year,
            'month' => $date->month,
        ]);

        $monthly->increment($column);

        $daily = Analysis::firstOrCreate([
            'date' => $date->toDateString(),
        ]);

        $daily->increment($column);

        Artisan::queue(GatherDailyMetrics::class, [
            '--date' => $date->toDateString(),
            '--tenants' => [tenant('id')],
        ]);
    }
}
