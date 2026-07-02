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
        $tables = ['transactions', 'machine_owner_transactions', 'brigade_transactions', 'worker_transactions'];
        
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('linked_work_type')->nullable()->after('amount');
                $table->unsignedBigInteger('linked_work_id')->nullable()->after('linked_work_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['transactions', 'machine_owner_transactions', 'brigade_transactions', 'worker_transactions'];
        
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropColumn(['linked_work_type', 'linked_work_id']);
            });
        }
    }
};
