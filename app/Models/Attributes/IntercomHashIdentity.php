<?php

namespace App\Models\Attributes;

use Webmozart\Assert\Assert;

trait IntercomHashIdentity
{
    public function getIntercomHashIdentityAttribute(): string
    {
        $secret = config('services.intercom.identity_verification_secret');

        Assert::stringNotEmpty($secret);

        return hash_hmac('sha256', (string) $this->id, $secret);
    }
}
