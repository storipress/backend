<?php

namespace App\Queue\Middleware;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\Middleware\WithoutOverlapping as BaseWithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class WithoutOverlapping extends BaseWithoutOverlapping
{
    /**
     * Process the job.
     *
     * @param  Job  $job
     * @param  callable  $next
     * @return mixed
     */
    public function handle($job, $next)
    {
        $lock = Cache::lock($this->getLockKey($job), $this->expiresAfter);

        if (tenancy()->central(fn () => $lock->get())) {
            try {
                $next($job);
            } finally {
                tenancy()->central(fn () => $lock->release());
            }
        } elseif (!is_null($this->releaseAfter)) {
            $job->release($this->secondsUntil($this->releaseAfter));
        }
    }
}
