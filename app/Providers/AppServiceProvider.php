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

        // Auto-migrate if new tables are missing
        if (!\Illuminate\Support\Facades\Cache::has('migrations_run_v4')) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('currency_rates')) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Auto-migration failed: ' . $e->getMessage());
                }
            } else {
                \Illuminate\Support\Facades\Cache::forever('migrations_run_v4', true);
            }
        }

        \App\Models\Contract::observe(\App\Observers\ContractObserver::class);
        \App\Models\WorkLog::observe(\App\Observers\WorkLogObserver::class);
        \App\Models\SystemSetting::observe(\App\Observers\SystemSettingObserver::class);
    }
}
