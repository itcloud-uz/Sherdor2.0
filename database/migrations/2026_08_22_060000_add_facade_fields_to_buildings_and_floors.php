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
            if (!Schema::hasColumn('buildings', 'facade_image')) {
                $table->string('facade_image')->nullable()->after('photo_path');
            }
            if (!Schema::hasColumn('buildings', 'facade_data')) {
                $table->longText('facade_data')->nullable()->after('facade_image');
            }
        });

        Schema::table('floors', function (Blueprint $table) {
            if (!Schema::hasColumn('floors', 'facade_polygon')) {
                $table->longText('facade_polygon')->nullable()->after('blueprint_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['facade_image', 'facade_data']);
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->dropColumn(['facade_polygon']);
        });
    }
};
