<?php

namespace App\GraphQL\Validators;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Nuwave\Lighthouse\Validation\Validator;

final class EnableSubscriptionInputValidator extends Validator
{
    /**
     * Return the validation rules.
     *
     * @return array<string, array<int, RequiredIf>>
     */
    public function rules(): array
    {
        $required = false;

        if ($this->args->has('subscription')) {
            $required = (bool) $this->args->arguments['subscription']->value;
        }

        return [
            'email' => [Rule::requiredIf($required)],
            // 'accent_color' => [Rule::requiredIf($required)], // @todo front-end not implement yet
            'currency' => [Rule::requiredIf($required)],
            'monthly_price' => [Rule::requiredIf($required)],
            'yearly_price' => [Rule::requiredIf($required)],
        ];
    }
}
