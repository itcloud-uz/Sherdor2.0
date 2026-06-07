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
            if (!Schema::hasColumn('buildings', 'basement_floors')) {
                $table->integer('basement_floors')->default(0)->after('total_floors');
            }
            if (!Schema::hasColumn('buildings', 'model_3d_path')) {
                $table->string('model_3d_path')->nullable()->after('blueprint_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (Schema::hasColumn('buildings', 'basement_floors')) {
                $table->dropColumn('basement_floors');
            }
            if (Schema::hasColumn('buildings', 'model_3d_path')) {
                $table->dropColumn('model_3d_path');
            }
        });
    }
};
