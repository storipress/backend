<?php

namespace App\GraphQL\Mutations\CustomDomain;

use App\Events\Entity\Domain\CustomDomainRemoved;
use App\GraphQL\Mutations\Mutation;
use App\Models\CustomDomain;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

class RemoveCustomDomain extends Mutation
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $this->authorize('write', CustomDomain::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        $domains = $tenant->custom_domains;

        if ($domains->isEmpty()) {
            return true;
        }

        CustomDomainRemoved::dispatch($tenant->id);

        UserActivity::log(
            name: 'publication.custom_domain.disable',
        );

        return true;
    }
}
