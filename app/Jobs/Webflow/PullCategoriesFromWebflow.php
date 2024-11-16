<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Enums\CustomField\Type;
use App\Models\Tenant;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\Webflow;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PullCategoriesFromWebflow extends WebflowPullJob
{
    /**
     * {@inheritdoc}
     */
    public string $rateLimiterName = 'webflow-api-unlimited';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?string $webflowId = null,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->webflowId ?: 'all',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:unlimited', $this->tenantId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $webflow = Webflow::retrieve();

            if (! $webflow->is_activated) {
                return;
            }

            $collection = $webflow->config->collections['desk'] ?? null;

            if (! is_array($collection)) {
                return;
            }

            if (empty($collection['mappings'])) {
                return;
            }

            $mapping = $this->mapping($collection);

            foreach ($this->items($collection['id']) as $item) {
                $extra = [];

                $attributes = [
                    'webflow_id' => $item->id,
                    'created_at' => $item->createdOn,
                    'updated_at' => $item->lastUpdated,
                    'deleted_at' => null,
                ];

                foreach (get_object_vars($item->fieldData) as $slug => $value) {
                    if (! isset($mapping[$slug])) {
                        continue;
                    }

                    $key = $mapping[$slug];

                    if (in_array($key, ['editors', 'writers'], true)) {
                        continue;
                    } elseif (Str::startsWith($key, 'custom_fields.')) {
                        $extra[$key] = $value;
                    } else {
                        $attributes[$key] = $value;
                    }
                }

                $desk = Desk::withTrashed()
                    ->withoutEagerLoads()
                    ->where(function (Builder $query) use ($item, $attributes) {
                        $query->where('webflow_id', '=', $item->id)
                            ->orWhere('slug', '=', $attributes['slug']);
                    })
                    ->updateOrCreate([], $attributes);

                foreach ($extra as $key => $values) {
                    $custom = data_get($desk, $key);

                    if (! ($custom instanceof CustomField)) {
                        continue;
                    }

                    $custom->group?->desks()->sync([$desk->id], false);

                    if (Type::reference()->is($custom->type)) {
                        $custom->values()->firstOrCreate([
                            'custom_field_morph_id' => $desk->id,
                            'custom_field_morph_type' => get_class($desk),
                            'type' => $custom->type,
                            'value' => Arr::wrap($values),
                        ]);

                        continue;
                    }

                    foreach (Arr::wrap($values) as $value) {
                        if (isset($value->url)) {
                            $value = $value->url;
                        }

                        if (Type::file()->is($custom->type)) {
                            $value = $this->toFile($value);
                        } elseif (Type::select()->is($custom->type)) {
                            $value = Arr::wrap($value);
                        }

                        $custom->values()->firstOrCreate([
                            'custom_field_morph_id' => $desk->id,
                            'custom_field_morph_type' => get_class($desk),
                            'type' => $custom->type,
                            'value' => $value,
                        ]);
                    }
                }

                ingest(
                    data: [
                        'name' => 'webflow.desk.pull',
                        'source_type' => 'desk',
                        'source_id' => $desk->id,
                        'webflow_id' => $item->id,
                    ],
                    type: 'action',
                );
            }
        });
    }
}
