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
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->decimal('estimated_budget', 15, 2)->default(0);
            $table->decimal('actual_expenses', 15, 2)->default(0);
            $table->enum('status', ['planning', 'active', 'completed'])->default('planning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['address', 'estimated_budget', 'actual_expenses', 'status']);
        });
    }
};
