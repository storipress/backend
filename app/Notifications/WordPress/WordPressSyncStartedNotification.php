<?php

declare(strict_types=1);

namespace App\Notifications\WordPress;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WordPressSyncStartedNotification extends Notification
{
    use HasMailChannel;

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
        return ['mail', 'broadcast', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this
            ->mail()
            ->tag('wordpress')
            ->metadata('tenant_id', $this->tenantId)
            ->replyTo('support@storipress.com')
            ->subject('[Storipress] Synchronization to WordPress Started')
            ->line(sprintf('We have started syncing the "%s" publication from Storipress to your WordPress site. You will receive an email once the sync is complete, or a notification if there\'s a failure. Please avoid editing content during the sync to prevent any data mismatches, as the Kanban board will not display posts.', $this->publicationName))
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
        return 'wordpress.synchronization.started';
    }
}
