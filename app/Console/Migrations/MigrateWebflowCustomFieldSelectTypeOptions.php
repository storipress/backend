<?php

namespace App\Console\Migrations;

use App\Enums\CustomField\Type;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type WebflowCollectionFields from WebflowConfiguration
 */
class MigrateWebflowCustomFieldSelectTypeOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-custom-field-select-type-options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $webflow = Webflow::retrieve();

            if (!$webflow->is_activated) {
                return;
            }

            if (!isset($webflow->config->collections['blog'])) {
                return;
            }

            $wFields = $webflow->config->collections['blog']['fields'];

            $fields = CustomField::withTrashed()
                ->where('type', '=', Type::select())
                ->lazyById(50);

            foreach ($fields as $field) {
                /** @var WebflowCollectionFields[0]|null $wField */
                $wField = Arr::first($wFields, function ($wField) use ($field) {
                    return $field->key === Str::snake(Str::camel($wField['slug']));
                });

                if ($wField === null) {
                    continue;
                }

                if (!isset($wField['validations']['options'])) {
                    continue;
                }

                $wOptions = $wField['validations']['options'];

                if (!is_array($wOptions)) {
                    continue;
                }

                $options = $field->options ?: [];

                $origins = $options['origins'] ?? [];

                unset($options['origins']);

                foreach ($origins as &$origin) {
                    unset($origin['origins']);
                }

                $origins[now()->timestamp] = $options;

                $options['origins'] = $origins;

                $options['type'] = 'select';

                $options['required'] = $wField['isRequired'];

                $options['multiple'] = false;

                $options['repeat'] = false;

                $options['choices'] = array_combine(
                    array_column($wOptions, 'name'),
                    array_column($wOptions, 'id'),
                );

                $field->update(['options' => $options]);
            }
        });

        return static::SUCCESS;
    }
}
