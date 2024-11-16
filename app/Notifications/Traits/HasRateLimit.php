<?php

namespace App\Notifications\Traits;

use Illuminate\Support\Facades\RateLimiter;

trait HasRateLimit
{
    public function rateLimit(string $rateLimitingKey, int $maxAttempts, int $decaySeconds): bool
    {
        $result = tenancy()->central(
            fn () => RateLimiter::attempt(
                key: sprintf('%s:%s', class_basename($this), $rateLimitingKey),
                maxAttempts: $maxAttempts,
                callback: fn () => true,
                decaySeconds: $decaySeconds,
            ),
        );

        return $result !== false;
    }
}
