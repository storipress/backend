<?php

namespace App\Jobs;

use App\Builder\ProgressTrackBuilder;
use App\Models\Tenant;
use App\Models\Tenants\Progress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Throwable;

class TrackJob implements ShouldQueue
{
    protected ?int $trackParentId = null;

    protected string $trackName = '';

    protected Tenant $tenant;

    protected ?ProgressTrackBuilder $track = null;

    public function start(): void
    {
        /** @var ProgressTrackBuilder $track */
        $track = $this->tenant->run(function () {
            if (Str::contains($this->trackName, 'site:create:')) {
                /** @var Progress $progress */
                $progress = Progress::where('name', 'site:create')->first();

                $this->trackParentId = $progress->id;
            }

            return new ProgressTrackBuilder($this->trackName, $this->trackParentId);
        });

        $this->track = $track;
    }

    protected function process(): void
    {
        // Process the job
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->start();

        $this->process();
    }

    public function failed(Throwable $exception): void
    {
        if ($this->track !== null) {
            $this->tenant->run(fn () => $this->track->failed());
        }

        throw $exception;
    }
}
