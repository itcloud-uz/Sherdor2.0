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
            $table->string('type', 20)->default('cash')->change();
            $table->foreignId('parent_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            
            $table->string('creditor_name')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('term_months')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->text('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id', 'creditor_name', 'total_amount', 'interest_rate', 
                'term_months', 'start_date', 'end_date', 'monthly_payment', 'description'
            ]);
        });
    }
};
