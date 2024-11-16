<?php

namespace App\Builder;

use App\Enums\Progress\ProgressState;
use App\Models\Tenants\Progress;

class ProgressTrackBuilder
{
    protected Progress $track;

    public function __construct(protected string $name, protected ?int $parent = null)
    {
        $this->track = Progress::create([
            'progress_id' => $parent,
            'name' => $name,
            'state' => ProgressState::running(),
        ]);
    }

    public function pending(): void
    {
        $this->track->update([
            'state' => ProgressState::pending(),
        ]);
    }

    public function failed(): void
    {
        $this->track->update([
            'state' => ProgressState::failed(),
        ]);
    }

    /**
     * @param  string[]|null  $data
     */
    public function done(?string $message = null, ?array $data = null): void
    {
        $track = $this->getTrack();

        if ($message !== null) {
            $track->message = $message;
        }

        if ($data !== null) {
            $track->data = $data;
        }

        $track->state = ProgressState::done();

        $track->save();

        $this->track->refresh();
    }

    public function message(string $message): void
    {
        $this->track->update([
            'message' => $message,
        ]);
    }

    public function getTrack(): Progress
    {
        return $this->track;
    }

    public function getTrackId(): int
    {
        return $this->track->id;
    }
}
