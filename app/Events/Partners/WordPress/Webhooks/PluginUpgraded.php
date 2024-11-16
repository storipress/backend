<?php

namespace App\Events\Partners\WordPress\Webhooks;

use Illuminate\Foundation\Events\Dispatchable;

class PluginUpgraded
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param array{
     *     version: string,
     *     site_name: string,
     *     url: string,
     *     rest_prefix: string,
     *     permalink_structure: mixed,
     *     activated_plugins: array{
     *          yoast_seo: bool,
     *          acf: bool,
     *     },
     * } $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {}
}
