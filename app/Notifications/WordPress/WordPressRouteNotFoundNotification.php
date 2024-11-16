<?php

declare(strict_types=1);

namespace App\Notifications\WordPress;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use App\Notifications\Traits\HasRateLimit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WordPressRouteNotFoundNotification extends Notification
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
        if (!$this->rateLimit($this->tenantId, 1, 60 * 60 * 24)) {
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
            ->line(sprintf('We ran into some issues with your "%s" publication when sending a request to your WordPress site. We need your help to check if any plugins related to the WordPress REST API are installed. This could involve modifications to the API path or disabling the REST API. For example, consider checking for the following plugins:', $this->publicationName))
            ->line(' - Hide My WP Ghost')
            ->line(' - Wordfence Security')
            ->line(' - WebTotem Security')
            ->line('If you need any help, just reply to this email and we will reply to you as soon as possible.')
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
        return 'wordpress.route.not_found';
    }
}
