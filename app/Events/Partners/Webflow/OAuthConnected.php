<?php

declare(strict_types=1);

namespace App\Events\Partners\Webflow;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;
use SocialiteProviders\Manager\OAuth2\User;

class OAuthConnected
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public User $user,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
