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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique()->nullable(); // for QR code matching
            $table->string('unit')->default('dona'); // kg, m, dona
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('min_quantity', 15, 2)->default(0); // alert threshold
            $table->decimal('price', 15, 2)->default(0); // estimated price per unit
            $table->string('category')->nullable(); // Sement, Yog'och, Temir vs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
