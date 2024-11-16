<?php

namespace App\Jobs\WordPress;

use App\Enums\CustomField\GroupType;
use App\Enums\CustomField\ReferenceTarget;
use App\Models\Tenant;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Support\Str;
use Throwable;

use function Sentry\captureException;

/**
 * @phpstan-type AcfDataObject object{
 *      id: string,
 *      group_id: string|null,
 *      acf_type: string,
 *      label: string,
 *      slug: string,
 *      name: string,
 *      attributes: AcfDataAttributes,
 *      create_at: string,
 *      update_at: string,
 *  }
 * @phpstan-type AcfDataAttributes object{
 *      location: array<array-key, array<array-key, object{
 *          param: string,
 *          operator: string,
 *          value: string,
 *      }>>|null,
 *      description: string|null,
 *      placeholder: string|null,
 *      field_type: string|null,
 *      taxonomy: string|null,
 *      type: string|null,
 *      required: int|null,
 *      choices: array<string, string>|null,
 *      multiple: int|null,
 *      min: int|string|null,
 *      max: int|string|null,
 *  }
 */
class PullAcfSchemaFromWordPress extends WordPressJob
{
    /**
     * @var array<string, string>
     */
    protected array $acfMapping = [
        'image' => 'file',
        'text' => 'text',
        'true_false' => 'boolean',
        'taxonomy' => 'reference',
        'oembed' => 'url',
        'url' => 'url',
        'select' => 'select',
        'checkbox' => 'select',
        'textarea' => 'text',
        'number' => 'number',
        'color' => 'color',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return $this->tenantId;
    }

    /**
     * Handle the given event.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            try {
                /**
                 * @var object{
                 *     ok: boolean,
                 *     data: array<int, AcfDataObject>
                 * } $response
                 */
                $response = app('wordpress')
                    ->request()
                    ->post('/storipress/acf-data');
            } catch (Throwable $e) {
                if (Str::contains($e->getMessage(), '4222001')) {
                    return; // acf is not activated
                }

                captureException($e);

                return;
            }

            if (! $response->ok) {
                return;
            }

            if (empty($response->data)) {
                return;
            }

            $group = CustomFieldGroup::withTrashed()
                ->withoutEagerLoads()
                ->updateOrCreate([
                    'key' => 'acf',
                ], [
                    'type' => GroupType::articleMetafield(),
                    'name' => 'ACF',
                    'description' => 'Advanced Custom Fields (WordPress)',
                    'deleted_at' => null,
                ]);

            foreach ($response->data as $field) {
                $this->acfField($group->id, $field);
            }
        });
    }

    /**
     * @param  AcfDataObject  $data
     */
    public function acfField(int $groupId, object $data): void
    {
        $attributes = $data->attributes;

        if (! is_object($attributes)) {
            return;
        }

        if (! isset($this->acfMapping[$attributes->type])) {
            return;
        }

        $type = $this->acfMapping[$attributes->type];

        $options = [
            'type' => $type,
            'repeat' => false,
            'required' => $attributes->required === 1,
            'placeholder' => $attributes->placeholder,
            'multiple' => $attributes->multiple === 1
                || $attributes->type === 'checkbox'
                || $attributes->field_type === 'multi_select'
                || $attributes->field_type === 'checkbox',
            'choices' => $attributes->choices,
            'acf' => $attributes,
        ];

        if ($attributes->type === 'textarea') {
            $options['multiline'] = true;
        }

        if ($type === 'reference' && isset($attributes->taxonomy)) {
            $options['target'] = match ($attributes->taxonomy) {
                'post_tag' => ReferenceTarget::tag,
                'category' => ReferenceTarget::desk,
                default => null,
            };
        }

        if ($attributes->type === 'number') {
            $options['min'] = is_int($attributes->min) ? $attributes->min : null;

            $options['max'] = is_int($attributes->max) ? $attributes->max : null;
        }

        $description = is_string($attributes->description) ? trim($attributes->description) : null;

        CustomField::withTrashed()
            ->withoutEagerLoads()
            ->updateOrCreate([
                'key' => $data->name,
            ], [
                'custom_field_group_id' => $groupId,
                'type' => $type,
                'name' => $data->label,
                'description' => is_not_empty_string($description)
                    ? $description
                    : null,
                'options' => $options,
                'deleted_at' => null,
            ]);
    }
}
