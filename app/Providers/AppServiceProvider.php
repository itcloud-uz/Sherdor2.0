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

        // Self-healing check for currency_rates table
        if (!\Illuminate\Support\Facades\Schema::hasTable('currency_rates')) {
            try {
                \Illuminate\Support\Facades\Schema::create('currency_rates', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('code', 3)->unique();
                    $table->decimal('rate', 12, 4)->default(1.0000);
                    $table->timestamps();
                });

                \Illuminate\Support\Facades\DB::table('currency_rates')->insert([
                    ['code' => 'UZS', 'rate' => 1.0000, 'created_at' => now(), 'updated_at' => now()],
                    ['code' => 'USD', 'rate' => 12800.0000, 'created_at' => now(), 'updated_at' => now()],
                    ['code' => 'EUR', 'rate' => 13800.0000, 'created_at' => now(), 'updated_at' => now()],
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to create currency_rates table: ' . $e->getMessage());
            }
        }

        \App\Models\Contract::observe(\App\Observers\ContractObserver::class);
        \App\Models\WorkLog::observe(\App\Observers\WorkLogObserver::class);
        \App\Models\SystemSetting::observe(\App\Observers\SystemSettingObserver::class);
    }
}
