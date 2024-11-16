<?php

namespace App\Models\Attributes;

use App\Enums\CustomField\GroupType;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCustomFields
{
    /**
     * @return Collection<int, CustomField>
     */
    public function getCustomFields(GroupType $type): Collection
    {
        return $this->getCustomFieldsFromCustomFieldGroups(
            CustomFieldGroup::query()
                ->with('customFields')
                ->where('type', '=', $type->value)
                ->get(),
        );
    }

    /**
     * @return Collection<int, CustomField>
     */
    public function getGroupableCustomFields(): Collection
    {
        if (! method_exists($this, 'groupable') || ! $this->isRelation('groupable')) {
            // @phpstan-ignore-next-line
            return new Collection();
        }

        return $this->getCustomFieldsFromCustomFieldGroups(
            $this->groupable()
                ->with('customFields')
                ->get(),
        );
    }

    /**
     * @param  Collection<int, CustomFieldGroup>  $groups
     * @return Collection<int, CustomField>
     */
    protected function getCustomFieldsFromCustomFieldGroups(Collection $groups): Collection
    {
        $fields = $groups
            ->pluck('customFields')
            ->flatten()
            ->values();

        // @phpstan-ignore-next-line
        return Collection::wrap($fields)->load(['values' => function (HasMany $builder) {
            $builder->where('custom_field_morph_id', '=', $this->getKey())
                ->where('custom_field_morph_type', '=', get_class($this));
        }]);
    }
}
