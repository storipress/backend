<?php

namespace App\Jobs\Entity\Desk;

use App\Events\Entity\Article\ArticleCreated;
use App\Events\Entity\Article\ArticleDeleted;
use App\Events\Entity\Article\ArticleDeskChanged;
use App\Events\Entity\Article\ArticleDuplicated;
use App\Events\Entity\Article\ArticlePublished;
use App\Events\Entity\Article\ArticleRestored;
use App\Events\Entity\Article\ArticleUnpublished;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Stage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Webmozart\Assert\Assert;

class CalculateDeskArticleNumber implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    protected int $readyId = 0;

    /**
     * Execute the job.
     */
    public function handle(
        ArticleCreated|
        ArticleDeleted|
        ArticleRestored|
        ArticleDuplicated|
        ArticleDeskChanged|
        ArticlePublished|
        ArticleUnpublished $event,
    ): void {
        $tenant = Tenant::withoutEagerLoads()
            ->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () use ($event) {
            $article = Article::withTrashed()
                ->withoutEagerLoads()
                ->find($event->articleId);

            if (!($article instanceof Article)) {
                return;
            }

            $desk = Desk::withTrashed()
                ->withoutEagerLoads()
                ->with(['desk'])
                ->find($article->desk_id);

            if (!($desk instanceof Desk)) {
                return;
            }

            $readyId = Stage::ready()->value('id');

            Assert::integer($readyId);

            $this->readyId = $readyId;

            $this->own($desk);

            if ($desk->desk !== null) {
                $this->sum($desk->desk);
            }
        });
    }

    protected function sum(Desk $desk): void
    {
        $desk->load('desks');

        $desks = $desk->desks;

        $desk->update([
            'draft_articles_count' => $desks->sum('draft_articles_count'),
            'published_articles_count' => $desks->sum('published_articles_count'),
            'total_articles_count' => $desks->sum('total_articles_count'),
        ]);
    }

    protected function own(Desk $desk): void
    {
        $total = $desk
            ->articles()
            ->count();

        $published = $desk
            ->articles()
            ->where('stage_id', '=', $this->readyId)
            ->where('published_at', '<=', now())
            ->count();

        $desk->update([
            'draft_articles_count' => $total - $published,
            'published_articles_count' => $published,
            'total_articles_count' => $total,
        ]);
    }
}
