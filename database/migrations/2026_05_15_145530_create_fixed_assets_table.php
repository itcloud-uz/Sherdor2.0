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
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // construction_equipment, office_furniture, light_structure, etc.
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2);
            $table->integer('service_life_months')->default(60);
            $table->enum('status', ['active', 'sold', 'retired'])->default('active');
            $table->text('description')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
