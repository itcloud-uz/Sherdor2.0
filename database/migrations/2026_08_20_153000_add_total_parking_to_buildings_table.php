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
        if (!Schema::hasColumn('buildings', 'total_parking')) {
            Schema::table('buildings', function (Blueprint $table) {
                $table->integer('total_parking')->default(0)->after('total_shops');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('buildings', 'total_parking')) {
            Schema::table('buildings', function (Blueprint $table) {
                $table->dropColumn('total_parking');
            });
        }
    }
};
