<?php

namespace App\Console\Migrations;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class MigrateArticleHtmlAndPlaintext extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:article:html-and-plaintext {--force}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        runForTenants(function (Tenant $tenant) {
            $query = DB::table('articles')
                ->select(['id', 'document']);

            if (!$this->option('force')) {
                $query->where(function (Builder $query) {
                    $query->whereNull('html')
                        ->orWhereNull('plaintext');
                });
            }

            $this->info(
                sprintf('Processing tenant %s...', $tenant->id),
            );

            $bar = $this->output->createProgressBar($query->count());

            $bar->start();

            /** @var stdClass $article */
            foreach ($query->lazyById(100) as $article) {
                $bar->advance();

                if (empty($article->document)) {
                    continue;
                }

                $context = json_decode($article->document, true);

                if (!is_array($context) || !isset($context['default'])) {
                    continue;
                }

                $html = app('prosemirror')->toHTML($context['default'], [
                    'client_id' => $tenant->id,
                    'article_id' => $article->id,
                ]);

                $plaintext = app('prosemirror')->toPlainText($context['default']);

                DB::table('articles')
                    ->where('id', '=', $article->id)
                    ->update([
                        'html' => $html,
                        'plaintext' => $plaintext,
                    ]);
            }

            $bar->finish();

            $this->newLine();
        });

        return static::SUCCESS;
    }
}
