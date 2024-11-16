<?php

namespace App\Observers;

use App\Enums\AutoPosting\State;
use App\Models\Tenants\Article;
use App\Models\Tenants\ArticleAutoPosting;
use Illuminate\Support\Arr;

class ArticleAutoPostingUpdatingObserver
{
    /**
     * Handle the "updated" event.
     */
    public function updated(Article $article): void
    {
        if (!$article->wasChanged('auto_posting')) {
            return;
        }

        $autoPostings = $article->autoPostings()
            ->where('state', State::initialized())
            ->get();

        if ($autoPostings->isEmpty()) {
            return;
        }

        $enable = [];

        /** @var array{text:string, enable:bool, user_id:string, scheduled_at:string}|array{} $twitter */
        $twitter = $article->twitter;

        if (!empty($twitter)) {
            $enable['twitter'] = Arr::get($twitter, 'enable', false);
        }

        /** @var array{text:string, enable:bool, page_id:string, scheduled_at:string}|array{} $facebook */
        $facebook = $article->facebook;

        if (!empty($facebook)) {
            $enable['facebook'] = Arr::get($facebook, 'enable', false);
        }

        if (empty($enable)) {
            return;
        }

        $autoPostings->each(function (ArticleAutoPosting $autoPosting) use ($enable) {
            $key = $autoPosting->platform;

            if (!empty($enable[$key])) {
                return;
            }

            $autoPosting->state = State::cancelled();

            $autoPosting->save();
        });
    }
}
