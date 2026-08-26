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
        // Alter type column from ENUM to VARCHAR(50)
        DB::statement("ALTER TABLE `counterparties` MODIFY COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'client'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `counterparties` MODIFY COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'client'");
    }
};
