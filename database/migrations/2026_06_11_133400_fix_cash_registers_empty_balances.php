<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
