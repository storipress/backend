<?php

namespace App\Events\Entity\Article;

use Illuminate\Foundation\Events\Dispatchable;

class AutoPostingPathUpdated
{
    use Dispatchable;

    public function __construct(
        public string $platform,
        public string $tenantId,
        public ?int $articleId = null,
    ) {
        //
    }
}
