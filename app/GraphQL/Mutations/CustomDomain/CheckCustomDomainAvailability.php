<?php

namespace App\GraphQL\Mutations\CustomDomain;

use App\Enums\CustomDomain\Group;
use App\GraphQL\Mutations\Mutation;
use App\Models\CustomDomain;

class CheckCustomDomainAvailability extends Mutation
{
    /**
     * @param  array{
     *     value: string,
     * }  $args
     * @return array{
     *     available: bool,
     *     site: bool,
     *     mail: bool,
     * }
     */
    public function __invoke($_, array $args): array
    {
        $this->authorize('write', CustomDomain::class);

        $domains = CustomDomain::where('domain', '=', $args['value'])->get([
            'id', 'group',
        ]);

        $site = $domains->where('group', '=', Group::site())->isEmpty();

        $mail = $domains->where('group', '=', Group::mail())->isEmpty();

        $redirect = $domains->where('group', '=', Group::redirect())->isEmpty();

        return [
            'available' => $site && $mail && $redirect,
            'site' => $site,
            'mail' => $mail,
            'redirect' => $redirect,
        ];
    }
}
