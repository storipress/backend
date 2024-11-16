<?php

namespace App\Events\Entity\Article;

use App\Events\Traits\HasAuthId;
use Illuminate\Foundation\Events\Dispatchable;

class ArticleUpdated
{
    use Dispatchable;
    use HasAuthId;

    /**
     * Create a new event instance.
     *
     * @param  array<int, int|string>  $changes
     */
    public function __construct(
        public string $tenantId,
        public int $articleId,
        public array $changes,
        public ?int $authId = null,
    ) {
        $this->setAuthIdIfRequired();
    }
}
