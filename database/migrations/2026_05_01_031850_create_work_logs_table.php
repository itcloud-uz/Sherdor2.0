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
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machinery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours_worked', 8, 2)->nullable();
            $table->decimal('volume_done', 10, 2)->nullable();
            $table->decimal('calculated_cost', 15, 2);
            $table->date('date');
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
