<?php

namespace App\Jobs\Scraper;

use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Scraper;
use App\Models\Tenants\ScraperArticle;
use App\Models\Tenants\Stage;
use App\Observers\TriggerSiteRebuildObserver;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use stdClass;
use Webmozart\Assert\Assert;

class ImportScrapedArticles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Lambda function name.
     */
    protected string $functionName = 'prosemirror-translator-dev';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        protected int $scraperId,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            tenancy()->initialize($this->tenantId);
        } catch (TenantCouldNotBeIdentifiedById) {
            return;
        }

        $scraper = Scraper::find($this->scraperId);

        if ($scraper === null) {
            return;
        }

        Article::disableSearchSyncing();

        TriggerSiteRebuildObserver::mute();

        $now = now();

        $emptyDoc = [
            'type' => 'doc',
            'content' => [],
        ];

        $defaultDesk = $this->defaultDesk();

        $readyStage = $this->readyStage();

        /** @var LazyCollection<int, ScraperArticle> $articles */
        $articles = $scraper->articles()
            ->whereNull('article_id')
            ->where('successful', true)
            ->lazyById();

        foreach ($articles as $article) {
            /** @var array{
             *     articleTitle: string,
             *     description?: string,
             *     articleBody: array<mixed>,
             *     publishDate?: string,
             *     articleCategory?: string,
             *     authorName?: string,
             * } $data
             */
            $data = $article->data;

            $content = empty($data['articleBody']) ? $emptyDoc : $data['articleBody'];

            $document = [
                'default' => $content,
            ];

            $model = new Article([
                'title' => $data['articleTitle'] ?: 'Untitled',
                'encryption_key' => base64_encode(random_bytes(32)),
            ]);

            $document['title'] = $this->transform($model->title)->result ?? $emptyDoc;

            if (!empty($data['authorName'])) {
                $model->shadow_authors = [$data['authorName']];
            }

            if (empty($data['description'])) {
                $document['blurb'] = $emptyDoc;
            } else {
                $model->blurb = $data['description'];

                $document['blurb'] = $this->transform($data['description'])->result ?? $emptyDoc;
            }

            if (empty($data['publishDate'])) {
                $model->published_at = $now;
            } else {
                try {
                    $model->published_at = Carbon::parse($data['publishDate']);
                } catch (InvalidFormatException) {
                    //
                }
            }

            if (empty($data['articleCategory'])) {
                $model->desk()->associate($defaultDesk);
            } else {
                $category = Str::of($data['articleCategory'])
                    ->trim()
                    ->limit(255, '');

                $model->desk()->associate(
                    Desk::firstOrCreate(['name' => $category]),
                );
            }

            $model->document = $document;

            $model->plaintext = app('prosemirror')->toPlainText($content);

            $model->stage()->associate($readyStage);

            $model->save();

            $model->update([
                'html' => app('prosemirror')->toHTML($content, [
                    'client_id' => $this->tenantId,
                    'article_id' => $model->id,
                ]),
            ]);

            $article->update([
                'article_id' => $model->id,
            ]);
        }

        TriggerSiteRebuildObserver::unmute();

        Article::enableSearchSyncing();
    }

    protected function defaultDesk(): Desk
    {
        $defaultDesk = Desk::first();

        if ($defaultDesk !== null) {
            return $defaultDesk;
        }

        return Desk::create(['name' => 'Uncategorised']);
    }

    protected function readyStage(): int
    {
        $stage = Stage::ready()->first(['id']);

        Assert::isInstanceOf($stage, Stage::class);

        return $stage->id;
    }

    protected function transform(string $document): stdClass
    {
        $content = app('aws') // @phpstan-ignore-line
            ->createLambda()
            ->invoke([
                'FunctionName' => $this->functionName,
                'InvocationType' => 'RequestResponse',
                'Payload' => json_encode([
                    'to' => 'PROSE_MIRROR',
                    'payload' => $document,
                ]),
            ])
            ->get('Payload')
            ->getContents();

        /** @var stdClass $context */
        $context = json_decode($content);

        return $context;
    }
}
