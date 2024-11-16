<?php

declare(strict_types=1);

namespace App\Events\Partners\WordPress;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class Connected
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     *
     * @param array{
     *     version: string,
     *     access_token: string,
     *     email: string,
     *     hash_key: string,
     *     username: string,
     *     user_id: string,
     *     url: string,
     *     site_name: string,
     *     prefix: string,
     *     permalink_structure: string,
     *     feature: array{
     *          site: bool,
     *          acf: bool,
     *          acf_pro: bool,
     *          yoast_seo: bool,
     *          rank_math: bool,
     *     },
     * } $payload
     */
    public function __construct(
        public string $tenantId,
        public array $payload,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
