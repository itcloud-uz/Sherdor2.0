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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('ITCloud Sher-Dor ERP');
            $table->string('logo_path')->nullable();
            $table->string('primary_theme_color')->default('#00F0FF'); // Default to Neon Cyan
            $table->string('neon_glow_intensity')->default('0 0 15px'); // Default intensity
            $table->string('default_currency')->default('USD');
            $table->decimal('tax_rate', 5, 2)->default(12.00);
            $table->decimal('default_penalty_rate', 5, 2)->default(0.10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
