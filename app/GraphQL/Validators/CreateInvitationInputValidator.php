<?php

namespace App\GraphQL\Validators;

use App\Models\Tenants\Desk;
use App\Models\Tenants\Invitation;
use App\Models\Tenants\User as TenantUser;
use App\Models\User;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

class CreateInvitationInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'bail',
                'required',
                'email:rfc,strict,dns,spoof',
                Rule::unique(Invitation::class, 'email')->withoutTrashed(),
                // custom unique validation rule
                function (string $attribute, string $value, callable $fail) {
                    $user = User::whereEmail($value)->first();

                    if ($user === null) {
                        return;
                    }

                    $exists = TenantUser::whereId($user->getKey())->exists();

                    if (!$exists) {
                        return;
                    }

                    $fail('unique');
                },
            ],
            'role_id' => [
                'required',
                'in:2,3,4,5',
            ],
            'desk_id' => [
                'bail',
                Rule::exists(Desk::class, 'id')
                    ->whereNull('desk_id'),
            ],
        ];
    }
}
