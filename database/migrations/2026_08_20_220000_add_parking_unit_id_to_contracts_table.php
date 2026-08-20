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
            if (!Schema::hasColumn('contracts', 'parking_unit_id')) {
                $table->foreignId('parking_unit_id')->nullable()->after('unit_id')->constrained('units')->onDelete('set null');
            }
            if (!Schema::hasColumn('contracts', 'parking_price')) {
                $table->decimal('parking_price', 15, 2)->default(0)->after('parking_unit_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'parking_unit_id')) {
                $table->dropForeign(['parking_unit_id']);
                $table->dropColumn('parking_unit_id');
            }
            if (Schema::hasColumn('contracts', 'parking_price')) {
                $table->dropColumn('parking_price');
            }
        });
    }
};
