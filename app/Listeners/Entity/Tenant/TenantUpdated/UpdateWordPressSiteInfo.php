<?php

namespace App\Listeners\Entity\Tenant\TenantUpdated;

use App\Events\Entity\Tenant\TenantUpdated;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\UploadedFileHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;

class UpdateWordPressSiteInfo implements ShouldQueue
{
    use InteractsWithQueue;
    use UploadedFileHelper;

    /**
     * Fields mapping.
     *
     * @var array<string, string>
     */
    protected array $mapping = [
        'name' => 'title',
        'description' => 'description',
        'timezone' => 'timezone',
        'logo' => 'site_logo',
        'favicon' => 'site_icon',
    ];

    /**
     * Handle the event.
     */
    public function handle(TenantUpdated $event): void
    {
        $tenant = Tenant::initialized()->find($event->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($event) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_connected) {
                return;
            }

            if (version_compare($wordpress->config->version, '0.0.14', '<')) {
                return;
            }

            if (! WordPress::retrieve()->config->feature['site']) {
                return;
            }

            $only = Arr::only($this->mapping, $event->changes);

            if (empty($only)) {
                return;
            }

            $data = $tenant->only(array_keys($only));

            $params = [];

            foreach ($this->mapping as $key => $wpKey) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];

                if ($key === 'logo') {
                    $logo = $tenant->logo_v2?->url ?: $tenant->logo?->url;

                    if (is_not_empty_string($logo)) {
                        $file = $this->toUploadedFile($logo);

                        if ($file) {
                            $media = app('wordpress')->media()->create($file, []);

                            $value = $media->id;
                        }
                    } else {
                        // remove logo.
                        $value = 0;
                    }
                } elseif ($key === 'favicon') {
                    $favicon = $tenant->favicon;

                    if (is_not_empty_string($favicon)) {
                        $file = $this->toUploadedFile($favicon);

                        if ($file) {
                            $media = app('wordpress')->media()->create($file, []);

                            $value = $media->id;
                        }
                    } else {
                        // remove icon.
                        $value = 0;
                    }
                }

                $params[$wpKey] = $value;
            }

            if (empty($params)) {
                return;
            }

            app('wordpress')->site()->update($params);
        });
    }
}
