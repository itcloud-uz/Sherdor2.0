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
        Schema::create('machine_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('service_name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('fuel', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_works');
    }
};
