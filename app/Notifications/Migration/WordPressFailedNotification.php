<?php

namespace App\Notifications\Migration;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WordPressFailedNotification extends WordPressNotification
{
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
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return $this
            ->mail()
            ->tag('migration.wordpress')
            ->metadata('tenant_id', $this->tenantId)
            ->error()
            ->replyTo('support@storipress.com')
            ->bcc(['kevin@storipress.com'])
            ->subject('[Storipress] Your WordPress migration has failed')
            ->line(sprintf('We encountered a few hiccups while moving your WordPress content to "%s" publication, but don\'t worry! Our system has taken note of these issues, and we\'ll keep you posted with updates via email as soon as we have more information.', $this->publicationName))
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
        return 'migration.wordpress.failed';
    }
}
