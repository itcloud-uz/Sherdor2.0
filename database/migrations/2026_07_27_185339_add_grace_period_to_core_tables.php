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
            $table->integer('grace_period_months')->nullable()->default(0)->after('term_months');
        });

        Schema::table('financial_liabilities', function (Blueprint $table) {
            $table->integer('grace_period_months')->nullable()->default(0)->after('term_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('grace_period_months');
        });

        Schema::table('financial_liabilities', function (Blueprint $table) {
            $table->dropColumn('grace_period_months');
        });
    }
};
