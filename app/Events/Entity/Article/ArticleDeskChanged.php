<?php

namespace App\Events\Entity\Article;

use Illuminate\Foundation\Events\Dispatchable;

class ArticleDeskChanged
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
        public int $originalDeskId,
    ) {
        //
    }
}
