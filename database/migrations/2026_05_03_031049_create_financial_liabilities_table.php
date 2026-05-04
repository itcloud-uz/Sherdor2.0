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
        Schema::create('financial_liabilities', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bank_loan', 'supplier_debt', 'partner_debt']);
            $table->string('creditor_name');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('term_months')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->enum('status', ['active', 'paid_off', 'defaulted'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_liabilities');
    }
};
