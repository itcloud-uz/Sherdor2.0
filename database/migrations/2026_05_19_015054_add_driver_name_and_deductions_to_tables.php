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
        Schema::table('machineries', function (Blueprint $table) {
            if (!Schema::hasColumn('machineries', 'driver_name')) {
                $table->string('driver_name')->nullable()->after('name');
            }
        });

        Schema::table('work_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('work_logs', 'deductions')) {
                $table->decimal('deductions', 15, 2)->default(0)->after('calculated_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machineries', function (Blueprint $table) {
            $table->dropColumn('driver_name');
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('deductions');
        });
    }
};
