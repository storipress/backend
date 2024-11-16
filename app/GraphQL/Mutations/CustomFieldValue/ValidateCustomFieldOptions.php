<?php

namespace App\GraphQL\Mutations\CustomFieldValue;

use App\Enums\CustomField\Type;
use App\Exceptions\ValidationException;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\WebflowReference;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

trait ValidateCustomFieldOptions
{
    /**
     * @throws ValidationException
     */
    protected function validateOptions(CustomField $field, mixed $data): bool
    {
        $options = Arr::except($field->options, ['type']);

        if ($field->type === null) {
            return true;
        }

        $rules = $this->optionsToRules($options);

        $nested = [];

        if (Type::text()->is($field->type)) {
            $rules[] = 'string';
        } elseif (Type::number()->is($field->type)) {
            $rules[] = 'numeric';
        } elseif (Type::color()->is($field->type)) {
            // do nothing
        } elseif (Type::url()->is($field->type)) {
            $rules[] = 'url';
        } elseif (Type::boolean()->is($field->type)) {
            $rules[] = 'boolean';
        } elseif (Type::select()->is($field->type)) {
            $rules[] = 'array';

            $choices = $field->options['choices'];

            if (is_string($choices)) {
                $choices = json_decode($choices, true);

                if (!is_array($choices)) {
                    $choices = [];
                }
            }

            $nested[] = Rule::in(array_values($choices));
        } elseif (Type::date()->is($field->type)) {
            $rules[] = 'date';
        } elseif (Type::file()->is($field->type)) {
            // do nothing
        } elseif (Type::richText()->is($field->type) || Type::json()->is($field->type)) {
            $rules[] = 'json';
        } elseif (Type::reference()->is($field->type)) {
            $model = $field->options['target'] ?? null;

            if ($model !== null && $model !== WebflowReference::class) {
                $rules[] = 'array';

                $nested[] = Rule::exists($model, 'id');
            }
        }

        if (empty($rules)) {
            return true;
        }

        $rules[] = 'nullable';

        $rules = ['value' => array_values(array_unique($rules))];

        if (in_array('array', $rules['value'], true)) {
            $rules['value.*'] = $nested;
        }

        $validator = Validator::make(['value' => $data], $rules);

        if (!$validator->passes()) {
            throw new ValidationException(
                $validator->errors()->first(),
                $validator,
            );
        }

        return true;
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $options
     * @return string[]
     */
    protected function optionsToRules(array $options): array
    {
        $rules = array_map(function ($value, $key) {
            if (in_array($key, ['required'], true)) {
                return $value ? $key : null;
            }

            if (in_array($key, ['min', 'max', 'regex'], true)) {
                if ($value === null || $value === '') {
                    return null;
                }

                return $key . ':' . $value;
            }

            if ($key === 'float') {
                return $value ? 'numeric' : 'integer';
            }

            return null;
        }, $options, array_keys($options));

        return array_filter($rules);
    }
}
