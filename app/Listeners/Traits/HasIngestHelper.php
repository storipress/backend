<?php

namespace App\Listeners\Traits;

use Illuminate\Support\Str;

trait HasIngestHelper
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function ingest(object $event, array $data = []): int
    {
        $name = Str::of(get_class($this))
            ->replace('\\', '.')
            ->remove(['App.Listeners.Partners', 'App.Listeners', 'RecordEvent'], false)
            ->trim('.')
            ->snake()
            ->replace('o_auth', 'oauth')
            ->explode('.')
            ->map(fn (string $value) => trim($value, '_'))
            ->filter()
            ->implode('.');

        $uuid = property_exists($event, 'uuid') ? $event->uuid : null;

        $actorId = property_exists($event, 'authId') ? $event->authId : null;

        $tenantId = property_exists($event, 'tenantId') ? $event->tenantId : null;

        return ingest(
            array_merge(
                $data,
                [
                    'name' => $name,
                    'event_id' => $uuid,
                    'actor_id' => $actorId,
                ],
            ),
            $tenantId,
        );
    }
}
