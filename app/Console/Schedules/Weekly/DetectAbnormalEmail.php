<?php

namespace App\Console\Schedules\Weekly;

use App\Console\Schedules\Command;
use App\Enums\Email\EmailAbnormalType;
use App\Models\Email;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DetectAbnormalEmail extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'detect:abnormal:email {--from= : Specify the start time}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $from = $this->option('from');

        $from = is_string($from)
            ? Carbon::parse($from)->toImmutable()
            : null;

        $to = now()->endOfDay()->subDays(45);

        $from = $from ?: $to->copy()->startOfDay()->subWeek();

        runForTenants(function (Tenant $tenant) use ($from, $to) {
            $emails = Email::withoutEagerLoads()
                ->with(['events' => function (HasMany $query) {
                    $query->orderBy('message_id')
                        ->orderBy('occurred_at')
                        ->select(['message_id', 'record_type', 'occurred_at']);
                }])
                ->whereBetween('created_at', [$from, $to])
                ->lazyById();

            foreach ($emails as $email) {
                $delivery = 0;
                $open = 0;
                $click = 0;
                $bounce = 0;
                $lastDeliveryTime = null;

                $saved = [];

                foreach ($email->events as $event) {
                    match ($event->record_type) {
                        'Delivery' => ++$delivery,
                        'Bounce' => ++$bounce,
                        'Open' => ++$open,
                        'Click' => ++$click,
                        default => null,
                    };

                    if ($lastDeliveryTime === null && $event->record_type === 'Delivery') {
                        $lastDeliveryTime = $event->occurred_at;
                    }

                    // open is too close with delivery (0s)
                    if ($event->record_type === 'Open' && $event->occurred_at->diffInSeconds($lastDeliveryTime ?: now()) === 0) {
                        $saved[] = EmailAbnormalType::deliveryAndOpenIsTooClose;
                    }
                    // click before open
                    elseif ($click > 0 && $open === 0) {
                        $saved[] = EmailAbnormalType::clickBeforeOpen;
                    }
                }

                foreach (array_unique($saved) as $type) {
                    $email->abnormal()->firstOrCreate([
                        'type' => $type,
                    ]);
                }
            }
        });

        return static::SUCCESS;
    }
}
