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
        Schema::table('buildings', function (Blueprint $table) {
            if (!Schema::hasColumn('buildings', 'shops_per_floor')) {
                $table->integer('shops_per_floor')->nullable()->default(0)->after('total_shops');
            }
            if (!Schema::hasColumn('buildings', 'apartments_per_floor')) {
                $table->integer('apartments_per_floor')->nullable()->default(0)->after('total_apartments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('buildings', 'shops_per_floor')) {
                $cols[] = 'shops_per_floor';
            }
            if (Schema::hasColumn('buildings', 'apartments_per_floor')) {
                $cols[] = 'apartments_per_floor';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
