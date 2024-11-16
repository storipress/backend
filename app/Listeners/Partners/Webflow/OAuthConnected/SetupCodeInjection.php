<?php

declare(strict_types=1);

namespace App\Listeners\Partners\Webflow\OAuthConnected;

use App\Events\Partners\Webflow\OAuthConnected;
use App\Listeners\Traits\HasIngestHelper;
use App\Models\Tenant;
use App\Models\Tenants\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

use function Sentry\captureException;

class SetupCodeInjection implements ShouldQueue
{
    use HasIngestHelper;
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OAuthConnected $event): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            try {
                $injection = Integration::find('code-injection');

                if (!($injection instanceof Integration)) {
                    return;
                }

                $data = $injection->data;

                $header = Arr::get($data, 'header');

                $script = '<meta name="robots" content="noindex">';

                if (!is_not_empty_string($header)) {
                    $header = $script;
                } elseif (!Str::contains($header, $script)) {
                    $header .= PHP_EOL . $script;
                }

                $data['header'] = $header;

                $injection->data = $data;

                $injection->activated_at = now();

                $injection->save();

                $this->ingest($event);
            } catch (Throwable $e) {
                captureException($e);
            }
        });
    }
}
