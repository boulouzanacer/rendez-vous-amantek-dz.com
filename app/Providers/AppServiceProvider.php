<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les dates de l'application (agenda, devis, rapports) sont affichées en français.
        Carbon::setLocale(config('app.locale'));
        Carbon::setFallbackLocale('fr');
    }
}
