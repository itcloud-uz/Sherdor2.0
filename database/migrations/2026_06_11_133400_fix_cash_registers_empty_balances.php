<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Self-healing check: Add missing initial balance columns if they don't exist
        if (!Schema::hasColumn('cash_registers', 'initial_balance_uzs')) {
            Schema::table('cash_registers', function (Blueprint $table) {
                $table->decimal('initial_balance_uzs', 15, 2)->default(0.00)->after('type');
            });
        }

        if (!Schema::hasColumn('cash_registers', 'initial_balance_usd')) {
            Schema::table('cash_registers', function (Blueprint $table) {
                $table->decimal('initial_balance_usd', 15, 2)->default(0.00)->after('initial_balance_uzs');
            });
        }

        if (!Schema::hasColumn('cash_registers', 'initial_balance_eur')) {
            Schema::table('cash_registers', function (Blueprint $table) {
                $table->decimal('initial_balance_eur', 15, 2)->default(0.00)->after('initial_balance_usd');
            });
        }

        // 2. Clean up any empty string or null values by setting them to 0.00
        DB::table('cash_registers')
            ->where('initial_balance_uzs', '')
            ->orWhereNull('initial_balance_uzs')
            ->update(['initial_balance_uzs' => 0.00]);

        DB::table('cash_registers')
            ->where('initial_balance_usd', '')
            ->orWhereNull('initial_balance_usd')
            ->update(['initial_balance_usd' => 0.00]);

        DB::table('cash_registers')
            ->where('initial_balance_eur', '')
            ->orWhereNull('initial_balance_eur')
            ->update(['initial_balance_eur' => 0.00]);

        DB::table('cash_registers')
            ->where('balance', '')
            ->orWhereNull('balance')
            ->update(['balance' => 0.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse operation needed for cleanup migration
    }
};
