<?php

namespace App\Console\Migrations;

use App\Enums\CustomField\Type;
use App\Models\Tenants\CustomFieldValue;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;

class MigrateWebflowCustomFieldReferenceTypeValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-custom-field-reference-type-value';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $fields = CustomFieldValue::withTrashed()
                ->where('type', '=', Type::reference())
                ->lazyById(50);

            $data = [];

            foreach ($fields as $field) {
                // get raw value.
                $values = $field->getRawOriginal('value');

                Assert::string($values);

                $values = Arr::wrap(json_decode($values, true));

                Assert::allValidArrayKey($values);

                $values = array_unique(array_map(fn ($value) => (string) $value, $values));

                $field->update(['value' => $values]);

                if ($field->trashed()) {
                    continue;
                }

                $key = sprintf(
                    '%s_%s_%s',
                    $field->custom_field_id,
                    $field->custom_field_morph_id,
                    $field->custom_field_morph_type,
                );

                $data[$key][] = $values;
            }

            foreach ($data as $key => $values) {
                if (count($values) === 1) {
                    continue;
                }

                $values = Arr::flatten($values);

                [$fieldId, $fieldMorphId, $fieldMorphType] = explode('_', $key);

                CustomFieldValue::where('custom_field_id', '=', $fieldId)
                    ->where('custom_field_morph_id', '=', $fieldMorphId)
                    ->where('custom_field_morph_type', '=', $fieldMorphType)
                    ->update([
                        'deleted_at' => now(),
                    ]);

                CustomFieldValue::create([
                    'custom_field_id' => $fieldId,
                    'custom_field_morph_id' => $fieldMorphId,
                    'custom_field_morph_type' => $fieldMorphType,
                    'type' => Type::reference,
                    'value' => array_values(array_unique($values)),
                ]);
            }
        });

        return static::SUCCESS;
    }
}
