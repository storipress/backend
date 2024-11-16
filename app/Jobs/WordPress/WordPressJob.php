<?php

namespace App\Jobs\WordPress;

use App\Queue\Middleware\WithoutOverlapping;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class WordPressJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 1;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->overlappingKey()))->dontRelease()];
    }

    /**
     * The job's unique key used for preventing overlaps.
     */
    abstract public function overlappingKey(): string;
}
