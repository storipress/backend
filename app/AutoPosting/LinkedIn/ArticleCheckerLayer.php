<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\ArticleCheckerLayer as BaseLayer;
use App\Exceptions\ErrorException;
use App\Models\Tenants\ArticleAutoPosting;

class ArticleCheckerLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    /**
     * {@inheritdoc}
     *
     * @param  array{}  $data
     * @param  array{}  $extra
     *
     * @throws ErrorException
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        $linkedin = $dispatcher->article->linkedin;

        $enable = $linkedin['enable'] ?? false;

        if (!$enable) {
            return false;
        }

        $posted = ArticleAutoPosting::where('article_id', $dispatcher->article->id)
            ->where('platform', 'linkedin')
            ->exists();

        return !$posted;
    }
}
