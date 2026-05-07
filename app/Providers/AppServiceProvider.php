<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL; // <-- 1. SHU QATORNI QO'SHING

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

        URL::forceScheme('https'); // <-- 2. VA SHU QATORNI QO'SHING

        \App\Models\Contract::observe(\App\Observers\ContractObserver::class);
        \App\Models\WorkLog::observe(\App\Observers\WorkLogObserver::class);
        \App\Models\SystemSetting::observe(\App\Observers\SystemSettingObserver::class);
    }
}
