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
        Schema::table('workers', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->enum('role', ['office', 'builder', 'driver'])->default('builder');
            $table->enum('salary_type', ['fixed_monthly', 'piece_rate', 'hourly'])->default('hourly');
            $table->decimal('base_rate', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'salary_type', 'base_rate']);
        });
    }
};
