<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class IntegrationKey implements ImplicitRule
{
    /**
     * Valid integration keys.
     *
     * @var bool[]
     */
    protected $keys = [
        'code-injection' => true,
        'disqus' => true,
        'facebook' => true,
        'twitter' => true,
        'google-analytics' => true,
        'google-adsense' => true,
        'google-news' => true,
        'mailchimp' => true,
        'slack' => true,
        'shopify' => true,
        'webflow' => true,
        'zapier' => true,
        'linkedin' => true,
        'hubspot' => true,
    ];

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        return isset($this->keys[$value]);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'exists';
    }
}
