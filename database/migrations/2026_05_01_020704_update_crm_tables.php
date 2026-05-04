<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
            $table->string('status')->default('new')->change();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['name', 'passport_data']);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('pinfl')->nullable();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('duration_months', 'term_months');
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->string('status')->default('active');
        });

        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->renameColumn('amount_due', 'expected_amount');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->renameColumn('expected_amount', 'amount_due');
            $table->dropColumn('paid_amount');
            // Reverting string to enum is tricky, we leave it as string.
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('term_months', 'duration_months');
            $table->dropColumn(['down_payment', 'status']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'passport_number', 'pinfl']);
            $table->string('name')->nullable();
            $table->string('passport_data')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
        });
    }
};
