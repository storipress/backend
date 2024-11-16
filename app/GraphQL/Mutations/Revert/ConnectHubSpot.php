<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Revert;

use App\Exceptions\ErrorCode;
use App\Exceptions\HttpException;
use App\Models\Tenant;

final readonly class ConnectHubSpot
{
    /**
     * @param  array{}  $args
     */
    public function __invoke(null $_, array $args): string
    {
        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $token = config('services.revert.public_token');

        if (!is_not_empty_string($token)) {
            throw new HttpException(ErrorCode::OAUTH_BAD_REQUEST);
        }

        $query = [
            'client_id' => '98c4040c-fc8c-4e36-872f-1afe30a7ed35',
            'redirect_uri' => 'https://app.revert.dev/oauth-callback/hubspot',
            'scope' => implode(' ', $this->scopes()),
            'state' => json_encode([
                'tenantId' => sprintf('%s-hubspot', $tenant->id),
                'revertPublicToken' => $token,
            ]),
        ];

        return sprintf(
            'https://app.hubspot.com/oauth/authorize?%s',
            http_build_query($query),
        );
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return [
            'crm.objects.companies.read',
            'crm.objects.companies.write',
            'crm.objects.contacts.read',
            'crm.objects.contacts.write',
            'crm.objects.custom.read',
            'crm.objects.custom.write',
            'crm.objects.deals.read',
            'crm.objects.deals.write',
            'crm.objects.line_items.read',
            'crm.objects.line_items.write',
            'crm.objects.marketing_events.read',
            'crm.objects.marketing_events.write',
            'crm.objects.owners.read',
            'crm.objects.quotes.read',
            'crm.objects.quotes.write',
            'crm.schemas.companies.read',
            'crm.schemas.companies.write',
            'crm.schemas.contacts.read',
            'crm.schemas.contacts.write',
            'crm.schemas.custom.read',
            'crm.schemas.deals.read',
            'crm.schemas.deals.write',
            'crm.schemas.line_items.read',
            'crm.schemas.quotes.read',
            'settings.users.read',
            'settings.users.teams.read',
            'settings.users.teams.write',
            'settings.users.write',
        ];
    }
}
