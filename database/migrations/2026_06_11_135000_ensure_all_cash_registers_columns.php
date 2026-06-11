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
        Schema::table('cash_registers', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_registers', 'initial_balance_uzs')) {
                $table->decimal('initial_balance_uzs', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('cash_registers', 'initial_balance_usd')) {
                $table->decimal('initial_balance_usd', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('cash_registers', 'initial_balance_eur')) {
                $table->decimal('initial_balance_eur', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('cash_registers', 'currency')) {
                $table->string('currency', 3)->default('UZS');
            }
            if (!Schema::hasColumn('cash_registers', 'balance')) {
                $table->decimal('balance', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('cash_registers', 'bank_name')) {
                $table->string('bank_name')->nullable();
            }
            if (!Schema::hasColumn('cash_registers', 'account_number')) {
                $table->string('account_number')->nullable();
            }
            if (!Schema::hasColumn('cash_registers', 'mfo_code')) {
                $table->string('mfo_code')->nullable();
            }
            if (!Schema::hasColumn('cash_registers', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
