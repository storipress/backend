<?php

namespace App\Notifications\Migration;

use Illuminate\Notifications\Messages\BroadcastMessage;

class WordPressProgressUpdatedNotification extends WordPressNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $tenantId,
        public int $progress,
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
        return ['broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'tenant_id' => $this->tenantId,
            'progress' => $this->progress,
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'migration.wordpress.progress.updated';
    }
}
