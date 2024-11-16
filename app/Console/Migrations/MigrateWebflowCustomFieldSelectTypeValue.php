<?php

namespace App\Console\Migrations;

use App\Enums\CustomField\Type;
use App\Models\Tenants\CustomFieldValue;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class MigrateWebflowCustomFieldSelectTypeValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:webflow-custom-field-select-type-value';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function () {
            $fields = CustomFieldValue::withTrashed()
                ->where('type', '=', Type::select())
                ->lazyById(50);

            foreach ($fields as $field) {
                if (is_array($field->value)) {
                    continue;
                }

                $field->update(['value' => Arr::wrap($field->value)]);
            }
        });

        return static::SUCCESS;
    }
}
