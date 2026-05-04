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
        Schema::create('company_balances', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->decimal('total_income', 20, 2)->default(0);
            $table->decimal('total_expense', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_balances');
    }
};
