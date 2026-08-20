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
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE units MODIFY COLUMN type VARCHAR(50) DEFAULT 'residential'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE units DROP CONSTRAINT IF EXISTS units_type_check");
            DB::statement("ALTER TABLE units ALTER COLUMN type TYPE VARCHAR(50)");
            DB::statement("ALTER TABLE units ALTER COLUMN type SET DEFAULT 'residential'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
