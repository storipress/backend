<?php

namespace App\Notifications\Webflow;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use App\Notifications\Traits\HasRateLimit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WebflowSchemaChangedNotification extends Notification
{
    use HasMailChannel;
    use HasRateLimit;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $tenantId,
        public string $publicationName,
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
        if (! $this->rateLimit($this->tenantId, 1, 60 * 60 * 24)) {
            return ['database'];
        }

        return ['mail', 'database', 'broadcast'];
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
            ->subject('[Storipress] Webflow collection structure changed')
            ->line(sprintf('We detected that the Webflow collection schema has changed for your "%s" publication. Please use the following link to check the field mapping. If you need any help, you can reply to this email for further assistance.', $this->publicationName))
            ->action('Check Webflow Mapping', app_url(sprintf('%s/preferences/publication/integrations?integration=webflow', $this->tenantId)));
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
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'webflow.schema.changed';
    }
}
