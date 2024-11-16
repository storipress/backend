<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification as BaseNotification;

/**
 * @phpstan-type BatchArray array{
 *       id: string,
 *       name: string,
 *       totalJobs: int,
 *       pendingJobs: int,
 *       processedJobs: int,
 *       progress: int,
 *       failedJobs: int,
 *       options: array<mixed>,
 *       createdAt: CarbonImmutable,
 *       cancelledAt: CarbonImmutable,
 *       finishedAt: CarbonImmutable
 *   }
 */
abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;
}
