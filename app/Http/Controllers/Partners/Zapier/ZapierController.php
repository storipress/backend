<?php

namespace App\Http\Controllers\Partners\Zapier;

use App\Http\Controllers\Partners\PartnerController;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;

class ZapierController extends PartnerController
{
    /**
     * @var array<string, string>
     */
    protected array $topics = [
        'article.created' => Article::class,
        'article.deleted' => Article::class,
        'article.published' => Article::class,
        'article.updated' => Article::class,
        'article.unpublished' => Article::class,
        'article.newsletter.sent' => Article::class,
        'article.stage.changed' => Article::class,
        'subscriber.created' => Subscriber::class,
    ];

    public function validate(mixed $topic): bool
    {
        return isset($this->topics[$topic]);
    }
}
