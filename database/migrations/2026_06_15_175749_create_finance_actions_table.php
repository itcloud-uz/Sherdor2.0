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
        Schema::create('finance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // 'create', 'update', 'delete', 'restore'
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('transaction_type')->nullable(); // 'income', 'expense'
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->default('UZS');
            $table->text('notes')->nullable();
            $table->json('details')->nullable(); // JSON log of the transaction data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_actions');
    }
};
