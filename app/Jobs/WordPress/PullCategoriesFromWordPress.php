<?php

namespace App\Jobs\WordPress;

use App\Models\Tenant;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Integrations\WordPress;
use Generator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Storipress\WordPress\Objects\Category;
use Storipress\WordPress\Objects\Category as CategoryObject;
use Tree\Node\Node;
use Tree\Visitor\PreOrderVisitor;

class PullCategoriesFromWordPress extends WordPressJob
{
    /**
     * @var array<int, Node>
     */
    public array $nodes = [];

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

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_activated) {
                return;
            }

            foreach ($this->categories() as $category) {
                $this->nodes[$category->id] = new Node($category);
            }

            foreach ($this->nodes as $node) {
                $category = $node->getValue();

                if (! ($category instanceof CategoryObject)) {
                    continue;
                }

                if ($category->parent === 0) {
                    continue;
                }

                if (! isset($this->nodes[$category->parent])) {
                    continue;
                }

                $this->nodes[$category->parent]->addChild($node);
            }

            foreach ($this->nodes as $node) {
                if (! $node->isRoot()) {
                    continue;
                }

                if (! ($node->getValue() instanceof CategoryObject)) {
                    continue;
                }

                $desk = $this->updateOrCreate($node->getValue(), null);

                ingest(
                    data: [
                        'name' => 'wordpress.desk.pull',
                        'source_type' => 'desk',
                        'source_id' => $desk->id,
                        'wordpress_id' => $node->getValue()->id,
                    ],
                    type: 'action',
                );

                $children = $node->accept(new PreOrderVisitor());

                if (! is_array($children)) {
                    continue;
                }

                // WordPress supports multi-layer categories, but Storipress only supports a single-layer structure.
                // Therefore, we need to assign the extra layer of sub-categories to the root layer desk.
                foreach ($children as $child) {
                    if ($child->isRoot()) {
                        continue;
                    }

                    if (! ($child->getValue() instanceof CategoryObject)) {
                        continue;
                    }

                    $this->updateOrCreate($child->getValue(), $desk->id);

                    ingest(
                        data: [
                            'name' => 'wordpress.desk.pull',
                            'source_type' => 'desk',
                            'source_id' => $desk->id,
                            'wordpress_id' => $node->getValue()->id,
                        ],
                        type: 'action',
                    );
                }
            }
        });
    }

    /**
     * 取得所有 categories。
     *
     * @return Generator<int, CategoryObject>
     */
    public function categories(): Generator
    {
        $api = app('wordpress')->category();

        $arguments = [
            'page' => 1,
            'per_page' => 25,
            'orderby' => 'id',
        ];

        if (is_int($this->wordpressId)) {
            $arguments['include'] = [$this->wordpressId];
        }

        do {
            $categories = $api->list($arguments);

            foreach ($categories as $category) {
                yield $category;
            }

            $arguments['page']++;
        } while (count($categories) === $arguments['per_page']);
    }

    /**
     * 新增或更新現有 desk。
     */
    public function updateOrCreate(Category $category, ?int $parent): Desk
    {
        $attributes = [
            'wordpress_id' => $category->id,
            'desk_id' => $parent,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'deleted_at' => null,
        ];

        return Desk::withTrashed()
            ->withoutEagerLoads()
            ->where(function (Builder $query) use ($category) {
                // if one of the "wordpress_id", and "slug" match the existing
                // desk, we will update that desk instead of creating a new one.
                $query->where('wordpress_id', '=', $category->id)
                    ->orWhere('slug', '=', $category->slug);
            })
            ->updateOrCreate([], $attributes);
    }
}
