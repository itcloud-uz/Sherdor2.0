<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);
        
        \App\Models\Contract::observe(\App\Observers\ContractObserver::class);
        \App\Models\WorkLog::observe(\App\Observers\WorkLogObserver::class);
        \App\Models\SystemSetting::observe(\App\Observers\SystemSettingObserver::class);
    }
}
