<?php

declare(strict_types=1);

namespace App\Notifications\Webflow;

use App\Notifications\Notification;
use App\Notifications\Traits\HasMailChannel;
use App\Notifications\Traits\HasRateLimit;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

class WebflowValidationNotification extends Notification
{
    use HasMailChannel;
    use HasRateLimit;

    /**
     * Create a new notification instance.
     *
     * @param  array<int, non-empty-string>  $validations
     */
    public function __construct(
        public string $tenantId,
        public string $publicationName,
        public string $type,
        public int $targetId,
        public array $validations,
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
        if (!$this->rateLimit($this->tenantId, 1, 60 * 60 * 1)) {
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
        $notification = $this
            ->mail()
            ->tag('webflow')
            ->metadata('tenant_id', $this->tenantId)
            ->error()
            ->replyTo('support@storipress.com')
            ->bcc(['kevin@storipress.com'])
            ->subject('[Storipress] Webflow synchronization has failed')
            ->line(sprintf('We encountered a few hiccups while synchronizing your "%s" publication\'s %s content to Webflow. The following fields did not pass validation. Please check them again.', $this->publicationName, $this->type));

        foreach ($this->validations as $validation) {
            $notification->line(sprintf(' - %s', $validation));
        }

        if ($this->type === 'article') {
            $notification->action(
                'Edit Article',
                sprintf('%s/articles/%d/edit', app_url($this->tenantId), $this->targetId),
            );
        } else {
            $notification->action('Visit Publication', app_url($this->tenantId));
        }

        return $notification;
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
            'group' => $this->type,
            'validations' => $this->validations,
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
            'type' => $this->type,
            'validations' => $this->validations,
        ]);
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType(): string
    {
        return 'webflow.synchronization.validation.failed';
    }
}
