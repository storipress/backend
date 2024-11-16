<?php

namespace App\Events\Entity\Article;

use Illuminate\Foundation\Events\Dispatchable;

class ArticleLived
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
    ) {
        //
    }
}
