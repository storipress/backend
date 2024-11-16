<?php

namespace App\GraphQL\Mutations\CustomDomain;

use App\Enums\CustomDomain\Group;
use App\Events\Entity\Domain\CustomDomainEnabled;
use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\GraphQL\Mutations\Mutation;
use App\Models\CustomDomain;
use App\Models\Tenant;
use App\Models\Tenants\UserActivity;
use Webmozart\Assert\Assert;

final class ConfirmCustomDomain extends Mutation
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $this->authorize('write', CustomDomain::class);

        $tenant = tenant();

        Assert::isInstanceOf($tenant, Tenant::class);

        if ($tenant->plan === 'free') {
            throw new HttpException(ErrorCode::CUSTOM_DOMAIN_PAID_REQUIRED);
        }

        if (! empty($tenant->site_domain) && ! empty($tenant->mail_domain)) {
            return false;
        }

        $domains = $tenant->custom_domains;

        if ($domains->isEmpty()) {
            return false;
        }

        if ($domains->where('ok', '=', false)->isNotEmpty()) {
            return false;
        }

        $attributes = [];

        $mapping = [
            'site' => 'site_domain',
            'mail' => 'mail_domain',
        ];

        foreach ($mapping as $key => $field) {
            $domain = $domains->where('group', '=', Group::{$key}())->first();

            if ($domain instanceof CustomDomain) {
                $attributes[$field] = $domain->domain;
            }
        }

        if (empty($attributes)) {
            return false;
        }

        $tenant->update($attributes);

        CustomDomainEnabled::dispatch($tenant->id);

        UserActivity::log(
            name: 'publication.custom_domain.enable',
            data: ['domain' => $tenant->site_domain ?: $tenant->mail_domain],
        );

        return true;
    }
}
