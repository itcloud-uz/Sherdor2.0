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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency')->default('UZS');
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer'])->default('cash');
            $table->foreignId('payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_hash')->unique()->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['currency', 'payment_method', 'payment_schedule_id', 'receipt_hash', 'notes']);
        });
    }
};
