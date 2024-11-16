<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use App\Models\Tenants\Tag;
use Generator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Storipress\WordPress\Objects\Tag as TagObject;

class PullTagsFromWordPress extends WordPressJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
        public ?int $wordpressId = null,
    ) {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf(
            '%s:%s',
            $this->tenantId,
            $this->wordpressId ?: 'all',
        );
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

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) {
            $wordpress = WordPress::retrieve();

            if (!$wordpress->is_activated) {
                return;
            }

            foreach ($this->tags() as $tag) {
                $attributes = [
                    'wordpress_id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description,
                    'deleted_at' => null,
                ];

                $model = Tag::withTrashed()
                    ->withoutEagerLoads()
                    ->where(function (Builder $query) use ($tag) {
                        // if one of the "wordpress_id", "name", and "slug" match the existing
                        // tag, we will update that tag instead of creating a new one.
                        $query->where('wordpress_id', '=', $tag->id)
                            ->orWhere('name', '=', $tag->name)
                            ->orWhere('slug', '=', $tag->slug);
                    })
                    ->updateOrCreate([], $attributes);

                ingest(
                    data: [
                        'name' => 'wordpress.tag.pull',
                        'source_type' => 'tag',
                        'source_id' => $model->id,
                        'wordpress_id' => $tag->id,
                    ],
                    type: 'action',
                );
            }
        });
    }

    /**
     * 取得所有 tags。
     *
     * @return Generator<int, TagObject>
     */
    public function tags(): Generator
    {
        $api = app('wordpress')->tag();

        $arguments = [
            'page' => 1,
            'per_page' => 25,
            'orderby' => 'id',
        ];

        if (is_int($this->wordpressId)) {
            $arguments['include'] = [$this->wordpressId];
        }

        do {
            $tags = $api->list($arguments);

            foreach ($tags as $tag) {
                yield $tag;
            }

            ++$arguments['page'];
        } while (count($tags) === $arguments['per_page']);
    }
}
