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
        Schema::create('machinery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('machine_name');
            $table->integer('hours_trips');
            $table->decimal('cost', 15, 2);
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_logs');
    }
};
