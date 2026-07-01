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
            $table->integer('apartments_per_floor')->default(0)->after('total_floors');
            $table->integer('commercial_per_floor')->default(0)->after('apartments_per_floor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('podezes', function (Blueprint $table) {
            $table->dropColumn(['apartments_per_floor', 'commercial_per_floor']);
        });
    }
};
