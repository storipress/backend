<?php

namespace App\GraphQL\Mutations\CustomField;

use App\Enums\CustomField\ReferenceTarget;
use App\Enums\CustomField\Type;
use App\Exceptions\ValidationException;
use BenSampo\Enum\Rules\EnumKey;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

trait HasCustomFieldOptions
{
    /**
     * @param  array<string, bool|float|int|string>  $options
     * @return array<string, bool|float|int|string>
     *
     * @throws ValidationException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateOptions(Type $type, array $options): array
    {
        $rules = match (Str::studly($type->value)) {
            'Text' => [
                'multiline' => ['boolean'],
                'min' => ['nullable', 'integer', 'min:1'],
                'max' => ['nullable', 'integer', 'between:1,65535', 'gt:min'],
                'regex' => ['nullable', 'string'],
            ],
            'Number' => [
                'float' => ['boolean'],
                'min' => ['nullable', 'numeric'],
                'max' => ['nullable', 'numeric', 'gt:min'],
            ],
            'Select' => [
                'choices' => ['required'],
                'multiple' => ['boolean'],
            ],
            'Date' => [
                'time' => ['boolean'],
            ],
            'Reference' => [
                'target' => ['required', new EnumKey(ReferenceTarget::class)], // @phpstan-ignore-line
                'multiple' => ['boolean'],
            ],
            default => [],
        };

        $rules = array_merge($rules, [
            'required' => ['boolean'],
            'repeat' => ['boolean'],
            'placeholder' => ['nullable', 'string'],
        ]);

        $validator = Validator::make($options, $rules);

        if (! $validator->passes()) {
            throw new ValidationException(
                $validator->errors()->first(),
                $validator,
            );
        }

        $data = $validator->validated();

        foreach ($data as &$datum) {
            if (is_string($datum) && empty($datum)) {
                $datum = null;
            }
        }

        if ($type->value === 'reference') {
            $data['target'] = ReferenceTarget::fromKey($data['target']);
        }

        $data['type'] = $type->value;

        return $data;
    }
}
