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
        Schema::table('podezes', function (Blueprint $table) {
            $table->integer('total_apartments')->default(0);
            $table->integer('total_commercial')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('podezes', function (Blueprint $table) {
            $table->dropColumn(['total_apartments', 'total_commercial']);
        });
    }
};
