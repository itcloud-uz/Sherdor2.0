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
        $tables = [
            'worker_transactions',
            'brigade_transactions',
            'user_transactions',
            'machinery_transactions',
            'machine_owner_transactions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('finance_transaction_id')->nullable();
                    // Do not add foreign key constraint explicitly to avoid SQLite cascade issues,
                    // we will manage deletion at application level.
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'worker_transactions',
            'brigade_transactions',
            'user_transactions',
            'machinery_transactions',
            'machine_owner_transactions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('finance_transaction_id');
                });
            }
        }
    }
};
