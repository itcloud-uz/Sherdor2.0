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
        Schema::table('brigade_works', function (Blueprint $table) {
            $table->string('work_name')->nullable();
            $table->string('building')->nullable();
            $table->string('block')->nullable();
            $table->string('floor')->nullable();
            $table->date('work_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brigade_works', function (Blueprint $table) {
            $table->dropColumn(['work_name', 'building', 'block', 'floor', 'work_date']);
        });
    }
};
