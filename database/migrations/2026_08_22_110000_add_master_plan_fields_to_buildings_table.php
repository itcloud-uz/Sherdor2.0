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
            if (!Schema::hasColumn('buildings', 'master_plan_image')) {
                $table->string('master_plan_image')->nullable()->after('photo_path');
            }
            if (!Schema::hasColumn('buildings', 'master_plan_polygon')) {
                $table->longText('master_plan_polygon')->nullable()->after('master_plan_image');
            }
            if (!Schema::hasColumn('buildings', 'master_plan_data')) {
                $table->longText('master_plan_data')->nullable()->after('master_plan_polygon');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['master_plan_image', 'master_plan_polygon', 'master_plan_data']);
        });
    }
};
