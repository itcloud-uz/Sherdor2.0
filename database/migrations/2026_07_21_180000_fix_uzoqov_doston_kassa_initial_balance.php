<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cash_registers')
            ->where('name', 'like', '%UZOQOV DOSTON%')
            ->update([
                'initial_balance_uzs' => 59980000.00,
            ]);
    }

    public function down(): void
    {
        DB::table('cash_registers')
            ->where('name', 'like', '%UZOQOV DOSTON%')
            ->update([
                'initial_balance_uzs' => 0.00,
            ]);
    }
};
