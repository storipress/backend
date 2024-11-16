<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateArticleEncryptionKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'article:encryption-key:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate article encryption key';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::where('initialized', '=', true)->lazyById();

        tenancy()->runForMultiple(
            $tenants,
            function (Tenant $tenant) {
                $this->info(
                    $tenant->id.' generating...',
                );

                $ids = DB::table('articles')
                    ->whereNull('encryption_key')
                    ->pluck('id')
                    ->toArray();

                foreach ($ids as $id) {
                    DB::table('articles')
                        ->where('id', '=', $id)
                        ->update(['encryption_key' => base64_encode(random_bytes(32))]);
                }
            },
        );

        return 0;
    }
}
