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
        // 1. Force currency of all registers to UZS
        DB::table('cash_registers')->update([
            'currency' => 'UZS',
            'initial_balance_usd' => 0,
            'initial_balance_eur' => 0,
        ]);

        // 2. Force currency of all transactions to UZS
        DB::table('transactions')->update([
            'currency' => 'UZS',
        ]);

        // 3. Force currency of all money transfers to UZS
        DB::table('money_transfers')->update([
            'sent_currency' => 'UZS',
            'received_currency' => 'UZS',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration is necessary as this is a cleanup/one-way migration.
    }
};
