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
        Schema::create('smeta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->onDelete('cascade');
            $table->string('block_name')->nullable()->default('Umumiy');
            $table->string('category_name');
            $table->string('item_name');
            $table->string('unit_type')->default('dona');
            $table->decimal('planned_quantity', 15, 2)->default(0);
            $table->decimal('planned_price', 15, 2)->default(0);
            $table->decimal('planned_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smeta_items');
    }
};
