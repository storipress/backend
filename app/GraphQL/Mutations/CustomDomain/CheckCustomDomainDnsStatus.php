<?php

namespace App\GraphQL\Mutations\CustomDomain;

use App\Enums\CustomDomain\Group;
use App\Events\Entity\Domain\CustomDomainCheckRequested;
use App\GraphQL\Mutations\Mutation;
use App\Models\CustomDomain;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Webmozart\Assert\Assert;

class CheckCustomDomainDnsStatus extends Mutation
{
    /**
     * @param  array{}  $args
     * @return non-empty-array<'mail'|'redirect'|'site', Collection<int, CustomDomain>|array{}>
     */
    public function __invoke($_, array $args): array
    {
        $this->authorize('write', CustomDomain::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $domains = $tenant->custom_domains;

        $groups = $domains->groupBy('group');

        if ($domains->where('ok', '=', false)->isNotEmpty()) {
            CustomDomainCheckRequested::dispatch($tenant->id);
        }

        $data = [];

        foreach (['site', 'mail', 'redirect'] as $key) {
            $data[$key] = $groups->get(Group::{$key}()->value, []);
        }

        return $data;
    }
}
