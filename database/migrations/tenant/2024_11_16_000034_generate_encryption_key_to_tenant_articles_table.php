<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class GenerateEncryptionKeyToTenantArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     *
     * @throws Exception
     */
    public function up(): void
    {
        DB::table('articles')
            ->oldest('id')
            ->select('id')
            ->chunk(30, function ($articles) {
                foreach ($articles as $article) {
                    DB::table('articles')
                        ->where('id', $article->id)
                        ->update([
                            'encryption_key' => base64_encode(random_bytes(32)),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
}
