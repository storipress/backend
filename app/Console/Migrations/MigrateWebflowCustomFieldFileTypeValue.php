<?php

namespace App\Console\Migrations;

use App\Enums\CustomField\Type;
use App\Models\Tenant;
use App\Models\Tenants\CustomFieldValue;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

class MigrateWebflowCustomFieldFileTypeValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-custom-field-file-type-value';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $endpoint = 'https://uploads-ssl.webflow.com/';

        runForTenants(function (Tenant $tenant) use ($endpoint) {
            $fields = CustomFieldValue::withTrashed()
                ->where('type', '=', Type::file())
                ->lazyById(50);

            foreach ($fields as $field) {
                $url = $field->value;

                if (! is_string($url)) {
                    continue;
                }

                if (! Str::startsWith($url, $endpoint)) {
                    $this->warn(
                        sprintf(
                            'Tenant: %s, value id: %d, unknown value: %s',
                            $tenant->id,
                            $field->id,
                            $url,
                        ),
                    );

                    continue;
                }

                $value = [
                    'key' => Str::after($url, $endpoint),
                    'url' => $url,
                    'size' => (int) (array_change_key_case(get_headers($url, true) ?: [])['content-length'] ?? 0),
                    'mime_type' => Arr::first((new MimeTypes())->getMimeTypes(pathinfo($url, PATHINFO_EXTENSION)), default: 'application/octet-stream'),
                ];

                $field->update(['value' => $value]);
            }
        });

        return static::SUCCESS;
    }
}
