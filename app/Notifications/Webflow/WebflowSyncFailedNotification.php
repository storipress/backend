<?php

declare(strict_types=1);

namespace App\Notifications\Webflow;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use App\Notifications\Traits\HasRateLimit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Arr;

class WebflowSyncFailedNotification extends Notification
{
    use HasMailChannel;
    use HasRateLimit;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $tenantId,
        public string $publicationName,
        public array $data,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $this->rateLimit($this->tenantId, 1, 60 * 60 * 1)) {
            return ['database'];
        }

        // @todo - webflow - remove env when stable
        return app()->isProduction()
            ? ['database']
            : ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this
            ->mail()
            ->tag('webflow')
            ->metadata('tenant_id', $this->tenantId)
            ->error()
            ->replyTo('support@storipress.com')
            ->bcc(['kevin@storipress.com'])
            ->subject('[Storipress] Webflow synchronization has failed')
            ->line(sprintf('We encountered a few hiccups while synchronizing your "%s" publication content to Webflow, but don\'t worry! Our system has taken note of these issues, and we\'ll keep you posted with updates via email as soon as we have more information.', $this->publicationName))
            ->action('Visit Publication', app_url($this->tenantId));
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenantId,
            ...$this->data,
        ];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(): string
    {
        return $this->broadcastType();
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'tenant_id' => $this->tenantId,
            ...Arr::except($this->data, ['trace']),
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'webflow.synchronization.failed';
    }
}
