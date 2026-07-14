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
        Schema::create('salary_periods', function (Blueprint $table) {
            $table->id();
            $table->string('employee_type'); // 'user' or 'worker'
            $table->unsignedBigInteger('employee_id');
            $table->decimal('salary', 15, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->index(['employee_type', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_periods');
    }
};
