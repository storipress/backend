<?php

namespace App\GraphQL\Validators;

use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class UpdateSiteInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var string $connection */
        $connection = config('tenancy.database.central_connection');

        $uniqueTable = sprintf('%s.tenants', $connection);

        return [
            'workspace' => [
                'bail',
                'sometimes',
                'required',
                'string',
                'min:5',
                'max:24',
                'regex:/^[0-9a-zA-Z][0-9a-zA-Z\\-]+[0-9a-zA-Z]$/',
                Rule::unique($uniqueTable, 'workspace')->ignore($tenant->id),
            ],
        ];
    }
}
