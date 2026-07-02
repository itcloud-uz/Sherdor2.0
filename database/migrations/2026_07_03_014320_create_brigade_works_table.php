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
        Schema::create('brigade_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brigade_id')->constrained('brigades')->cascadeOnDelete();
            $table->string('floor_info')->nullable();
            $table->string('room_info')->nullable();
            $table->string('service_name')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('measure1', 10, 4)->default(0);
            $table->decimal('measure2', 10, 4)->default(0);
            $table->decimal('total_measure', 10, 4)->default(0);
            $table->decimal('quantity', 10, 4)->default(1);
            $table->decimal('total', 10, 4)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('calculated_sum', 15, 2)->default(0);
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brigade_works');
    }
};
