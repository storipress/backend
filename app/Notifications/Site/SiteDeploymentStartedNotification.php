<?php

namespace App\Notifications\Site;

use App\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class SiteDeploymentStartedNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $tenantId,
        public int $releaseId,
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
        return ['database', 'broadcast'];
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
            'release_id' => $this->releaseId,
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
            'data' => [
                'release_id' => $this->releaseId,
            ],
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'site.deployment.started';
    }
}
