<?php

declare(strict_types=1);

namespace App\Notifications\WordPress;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use App\Notifications\Traits\HasRateLimit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WordPressSyncFailedNotification extends Notification
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
        if (! $this->rateLimit($this->tenantId, 1, 60 * 60 * 1)) {
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
            ->tag('wordpress')
            ->metadata('tenant_id', $this->tenantId)
            ->error()
            ->replyTo('support@storipress.com')
            ->bcc(['kevin@storipress.com'])
            ->subject('[Storipress] WordPress synchronization has failed')
            ->line(sprintf('We encountered a few hiccups while synchronizing your "%s" publication content to WordPress, but don\'t worry! Our system has taken note of these issues, and we\'ll keep you posted with updates via email as soon as we have more information.', $this->publicationName))
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
        return 'wordpress.synchronization.failed';
    }
}
