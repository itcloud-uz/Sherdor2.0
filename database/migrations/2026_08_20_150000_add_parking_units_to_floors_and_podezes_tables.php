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
        if (!Schema::hasColumn('floors', 'total_parking_units')) {
            Schema::table('floors', function (Blueprint $table) {
                $table->integer('total_parking_units')->default(0)->after('total_commercial_units');
            });
        }

        if (!Schema::hasColumn('podezes', 'total_parking')) {
            Schema::table('podezes', function (Blueprint $table) {
                $table->integer('total_parking')->default(0)->after('total_commercial');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('floors', 'total_parking_units')) {
            Schema::table('floors', function (Blueprint $table) {
                $table->dropColumn('total_parking_units');
            });
        }

        if (Schema::hasColumn('podezes', 'total_parking')) {
            Schema::table('podezes', function (Blueprint $table) {
                $table->dropColumn('total_parking');
            });
        }
    }
};
