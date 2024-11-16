<?php

namespace App\Events\Partners\Shopify;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class WebhookReceived
{
    use Dispatchable;

    /**
     * @param  array{
     *     domain: string,
     *     topic: string,
     *     id: int,
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     accepts_marketing: bool,
     *     verified_email?: bool,
     *     customer: array{
     *         id: int,
     *         email: string,
     *     },
     * }  $payload
     * @param  Collection<int, string>  $tenantIds
     */
    public function __construct(
        public string $topic,
        public array $payload,
        public Collection $tenantIds,
    ) {
        //
    }
}
