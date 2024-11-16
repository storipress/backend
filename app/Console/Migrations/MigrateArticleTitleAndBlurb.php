<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use Illuminate\Console\Command;

class MigrateArticleTitleAndBlurb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:article:title-and-blurb {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate article title and blurb to hocuspocus format';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prosemirror = app('prosemirror');

        $force = $this->option('force');

        $emptyDoc = [
            'type' => 'doc',
            'content' => [],
        ];

        runForTenants(
            function (Tenant $tenant) use ($emptyDoc, $force, $prosemirror) {
                $this->info(
                    sprintf('Migrating %s...', $tenant->id),
                );

                $progress = $this->output->createProgressBar(
                    Article::withTrashed()->count(),
                );

                $progress->start();

                /** @var Article $article */
                foreach (Article::withTrashed()->lazyById() as $article) {
                    $document = $article->document;

                    foreach (['title', 'blurb'] as $field) {
                        if (! $force && ! empty($document[$field])) {
                            continue;
                        }

                        if (empty(trim($article->{$field} ?: ''))) {
                            $document[$field] = $emptyDoc;

                            continue;
                        }

                        $document[$field] = $prosemirror->toProseMirror($article->{$field}) ?: $emptyDoc;
                    }

                    $article->document = $document;

                    $article->save();

                    $progress->advance();
                }

                $progress->finish();

                $this->newLine();
            },
        );

        return static::SUCCESS;
    }
}
