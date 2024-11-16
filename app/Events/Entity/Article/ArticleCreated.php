<?php

namespace App\Events\Entity\Article;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class ArticleCreated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
