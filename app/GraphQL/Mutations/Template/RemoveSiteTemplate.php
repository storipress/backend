<?php

namespace App\GraphQL\Mutations\Template;

use App\Models\Tenant;
use App\Models\Tenants\Template;
use App\Models\Tenants\UserActivity;

final class RemoveSiteTemplate
{
    /**
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): bool
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            return false;
        }

        $cleanup = $tenant->update([
            'custom_site_template' => false,
            'custom_site_template_path' => null,
        ]);

        $removed = Template::where('group', 'LIKE', 'site-%')->delete();

        UserActivity::log(
            name: 'site.template.remove',
        );

        return $cleanup && $removed;
    }
}
