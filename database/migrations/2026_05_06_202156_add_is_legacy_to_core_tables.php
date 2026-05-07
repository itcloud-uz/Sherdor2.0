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
        $tables = ['clients', 'contracts', 'payment_schedules', 'transactions', 'machinery', 'workers'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('is_legacy')->default(false);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['clients', 'contracts', 'payment_schedules', 'transactions', 'machinery', 'workers'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_legacy')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('is_legacy');
                });
            }
        }
    }
};
