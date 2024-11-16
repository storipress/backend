<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Webflow;

use App\Enums\CustomField\GroupType;
use App\Enums\Webflow\CollectionType;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Integrations\Webflow;
use App\Models\Tenants\WebflowReference;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type WebflowCollection from WebflowConfiguration
 * @phpstan-import-type WebflowCollectionFields from WebflowConfiguration
 */
final readonly class UpdateWebflowCollectionMapping
{
    /**
     * @param  array{
     *     type: CollectionType,
     *     value: array<int, array{
     *         webflow_id: string,
     *         storipress_id: string,
     *     }>,
     * }  $args
     */
    public function __invoke(null $_, array $args): true
    {
        $webflow = Webflow::retrieve();

        $type = $args['type']->value;

        if (!isset($webflow->config->collections[$type])) {
            throw new HttpException(ErrorCode::WEBFLOW_MISSING_COLLECTION_ID);
        }

        /** @var WebflowCollection $collection */
        $collection = $webflow->config->collections[$type];

        $candidates = $this->candidates($collection['fields']);

        $required = $this->required($collection['fields']);

        $group = $this->group($args['type']);

        $mappings = $collection['mappings'] ?? [];

        foreach ($args['value'] as ['webflow_id' => $source, 'storipress_id' => $target]) {
            if ($target !== '__new__') {
                $options = $candidates[$source] ?? [];

                if (!in_array($target, $options, true)) {
                    continue;
                }

                $mappings[$source] = $target;
            } elseif ($group instanceof CustomFieldGroup) {
                foreach ($collection['fields'] as $field) {
                    if ($source !== $field['id']) {
                        continue;
                    }

                    $mappings[$source] = sprintf(
                        'custom_fields.%d',
                        $this->toCustomField($group, $field)->id,
                    );
                }
            }
        }

        $webflow->config->update([
            'collections' => [
                $type => [
                    'mappings' => $mappings,
                ],
            ],
            'onboarding' => [
                'mapping' => [
                    $type => Arr::has(array_filter($mappings), $required),
                ],
            ],
        ]);

        return true;
    }

    /**
     * @param  WebflowCollectionFields  $fields
     * @return array<string, array<int, string>>
     */
    protected function candidates(array $fields): array
    {
        $candidates = [];

        foreach ($fields as $field) {
            $candidates[$field['id']] = array_column(
                $field['candidates'],
                'value',
            );
        }

        return $candidates;
    }

    /**
     * @param  WebflowCollectionFields  $fields
     * @return array<int, string>
     */
    protected function required(array $fields): array
    {
        $required = [];

        foreach ($fields as $field) {
            if (!$field['isRequired']) {
                continue;
            }

            $required[] = $field['id'];
        }

        return $required;
    }

    public function group(CollectionType $type): ?CustomFieldGroup
    {
        $group = CustomFieldGroup::withoutEagerLoads();

        return match ($type->value) {
            CollectionType::blog => $group->firstOrCreate(
                [
                    'key' => 'webflow',
                    'type' => GroupType::articleMetafield(),
                ],
                [
                    'name' => 'Webflow',
                    'description' => 'Webflow Custom Fields (Auto-Generated)',
                ],
            ),

            CollectionType::desk => $group->firstOrCreate(
                [
                    'key' => 'webflow_desk',
                    'type' => GroupType::deskMetafield(),
                ],
                [
                    'name' => 'Webflow (Desk)',
                    'description' => 'Webflow Custom Fields (Auto-Generated)',
                ],
            ),

            CollectionType::tag => $group->firstOrCreate(
                [
                    'key' => 'webflow_tag',
                    'type' => GroupType::tagMetafield(),
                ],
                [
                    'name' => 'Webflow (Tag)',
                    'description' => 'Webflow Custom Fields (Auto-Generated)',
                ],
            ),

            default => null,
        };
    }

    /**
     * @param WebflowCollectionFields[0] $field
     */
    public function toCustomField(CustomFieldGroup $group, array $field): CustomField
    {
        $type = WebflowConfiguration::toStoripressType($field['type']);

        if ($type === null) {
            throw new HttpException(ErrorCode::WEBFLOW_UNSUPPORTED_COLLECTION_FIELDS);
        }

        $options = [
            'type' => $type,
            'required' => $field['isRequired'],
            'repeat' => false,
        ];

        $extraOptions = sprintf('to%sOptions', $field['type']);

        if ($field['validations'] !== null && method_exists($this, $extraOptions)) {
            $options = array_merge($options, $this->{$extraOptions}($field['validations']));
        }

        $now = now();

        $key = Str::snake(Str::camel($field['slug']));

        $model = new CustomField([
            'key' => $key,
            'type' => $type,
            'name' => $field['displayName'],
            'description' => $field['helpText'],
            'options' => $options,
        ]);

        try {
            $group->customFields()->save($model);
        } catch (UniqueConstraintViolationException) {
            CustomField::where('custom_field_group_id', '=', $group->id)
                ->where('key', '=', $key)
                ->sole()
                ->update([
                    'key' => sprintf('%s_%d', $key, $now->timestamp),
                    'deleted_at' => $now,
                ]);

            $group->customFields()->save($model);
        }

        return $model;
    }

    /**
     * @param  array{
     *      singleLine?: bool,
     *      maxLength?: int,
     *      minLength?: int,
     *  }  $validations
     * @return array<string, mixed>
     */
    public function toPlainTextOptions(array $validations): array
    {
        return [
            'multiline' => !($validations['singleLine'] ?? false),
            'max' => $validations['maxLength'] ?? null,
            'min' => $validations['minLength'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     maxLength?: int,
     *     minLength?: int,
     * }  $validations
     * @return array<string, mixed>
     */
    public function toRichTextOptions(array $validations): array
    {
        return [
            'max' => $validations['maxLength'] ?? null,
            'min' => $validations['minLength'] ?? null,
        ];
    }

    /**
     * @param  array{
     *      maxValue?: int,
     *      minValue?: int,
     *  }  $validations
     * @return array<string, mixed>
     */
    public function toNumberOptions(array $validations): array
    {
        return [
            'max' => $validations['maxValue'] ?? null,
            'min' => $validations['minValue'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     options: non-empty-array<int, array{
     *         id: string,
     *         name: string,
     *     }>,
     * }  $validations
     * @return array<string, mixed>
     */
    public function toOptionOptions(array $validations): array
    {
        $options = $validations['options'];

        return [
            'choices' => array_combine(
                array_column($options, 'name'),
                array_column($options, 'id'),
            ),
            'multiple' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $validations
     * @return array<string, mixed>
     */
    public function toMultiImageOptions(array $validations): array
    {
        return [
            'repeat' => true,
        ];
    }

    /**
     * @param array{
     *     collectionId: non-empty-string,
     * } $validations
     * @return array{
     *     target: class-string,
     *     collection_id: non-empty-string,
     *     multiple: false,
     * }
     */
    public function toReferenceOptions(array $validations): array
    {
        return [
            'target' => WebflowReference::class,
            'collection_id' => $validations['collectionId'],
            'multiple' => false,
        ];
    }

    /**
     * @param array{
     *      collectionId: non-empty-string,
     *  } $validations
     *  @return array{
     *      target: class-string,
     *      collection_id: non-empty-string,
     *      multiple: true,
     *  }
     */
    public function toMultiReferenceOptions(array $validations): array
    {
        return [
            'target' => WebflowReference::class,
            'collection_id' => $validations['collectionId'],
            'multiple' => true,
        ];
    }
}
