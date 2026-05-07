<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Drop foreign key if it was explicitly named, then change column
            // In SQLite, altering foreign keys can be tricky, but making it nullable is generally supported.
            $table->foreignId('unit_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable(false)->change();
        });
    }
};
