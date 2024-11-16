<?php

namespace App\Notifications;

use App\Notifications\Migration\WordPressNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UserRoleChangedNotification extends WordPressNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $tenantId,
        public int $userId,
        public string $role,
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
            'data' => [
                'user_id' => $this->userId,
                'role' => $this->role,
            ],
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'user.role.changed';
    }
}
