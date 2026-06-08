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
        Schema::table('units', function (Blueprint $table) {
            $table->index(['floor_id', 'type', 'status'], 'units_floor_type_status_index');
            $table->index(['floor_id', 'unit_number'], 'units_floor_unit_number_index');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->index(['name', 'total_floors'], 'buildings_name_total_floors_index');
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->index(['building_id', 'floor_number'], 'floors_building_floor_number_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_floor_type_status_index');
            $table->dropIndex('units_floor_unit_number_index');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropIndex('buildings_name_total_floors_index');
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->dropIndex('floors_building_floor_number_index');
        });
    }
};
