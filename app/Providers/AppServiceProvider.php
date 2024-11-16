<?php

namespace App\Providers;

use App\Jobs\Typesense\MakeSearchable;
use App\Jobs\Typesense\RemoveFromSearch;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Scout\Scout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreMigrations();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Cashier::calculateTaxes();

        Scout::makeSearchableUsing(MakeSearchable::class);

        Scout::removeFromSearchUsing(RemoveFromSearch::class);
    }
}
