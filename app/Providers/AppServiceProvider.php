<?php

namespace App\Providers;

use App\Services\GenerateDokumenService;
use App\Services\LlmAutoFillService;
use App\Services\PermohonanSyncService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermohonanSyncService::class);
        $this->app->singleton(GenerateDokumenService::class);
        $this->app->singleton(LlmAutoFillService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');
    }
}
