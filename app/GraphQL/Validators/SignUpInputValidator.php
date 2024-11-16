<?php

namespace App\GraphQL\Validators;

use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Validation\Validator;

final class SignUpInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'email' => [
                'bail',
                'required',
                'email:rfc,strict,dns,spoof',
                'unique:App\\Models\\User,email',
            ],
        ];

        $email = $this->arg('email');

        $code = $this->arg('appsumo_code');

        if (empty($email) || empty($code) || !is_string($code)) {
            return $rules;
        }

        $known = Cache::get('appsumo-' . $code);

        if (empty($known) || $known !== $email) {
            return $rules;
        }

        return [];
    }
}
