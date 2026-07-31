<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("UPDATE machine_works SET date = REPLACE(date, '0221-', '2021-') WHERE date LIKE '0221-%' OR date LIKE '221-%'");
            DB::statement("UPDATE machine_owner_transactions SET date = REPLACE(date, '0221-', '2021-') WHERE date LIKE '0221-%' OR date LIKE '221-%'");
        } catch (\Throwable $e) {
            // Ignore if columns or syntax vary across drivers
        }
    }

    public function down(): void
    {
    }
};
