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
        Schema::table('counterparties', function (Blueprint $table) {
            if (!Schema::hasColumn('counterparties', 'account_number')) {
                $table->string('account_number')->nullable()->after('balance');
            }
            if (!Schema::hasColumn('counterparties', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('counterparties', 'inn')) {
                $table->string('inn')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('counterparties', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('inn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('counterparties', 'account_number')) {
                $columns[] = 'account_number';
            }
            if (Schema::hasColumn('counterparties', 'bank_name')) {
                $columns[] = 'bank_name';
            }
            if (Schema::hasColumn('counterparties', 'inn')) {
                $columns[] = 'inn';
            }
            if (Schema::hasColumn('counterparties', 'is_active')) {
                $columns[] = 'is_active';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
