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
        Schema::create('brigade_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brigade_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'payment', 'advance_payment', 'penalty'
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brigade_transactions');
    }
};
