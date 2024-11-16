<?php

namespace App\Notifications\Migration;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WordPressSucceededNotification extends WordPressNotification
{
    /**
     * Create a new notification instance.
     *
     * @param array{
     *     articles: int,
     * } $statistics
     */
    public function __construct(
        public string $tenantId,
        public string $publicationName,
        public array $statistics,
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
            ->success()
            ->replyTo('support@storipress.com')
            ->subject('[Storipress] Your WordPress migration has completed')
            ->greeting('Great news!')
            ->line(sprintf('Your "%s" publication\'s WordPress migration is completed, and we\'ve successfully imported a total of %s articles.', $this->publicationName, number_format($this->statistics['articles'])))
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
            'statistics' => $this->statistics,
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
            'statistics' => $this->statistics,
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'migration.wordpress.succeeded';
    }
}
