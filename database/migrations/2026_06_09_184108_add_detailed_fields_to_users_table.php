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
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable(); // 'male', 'female'
            $table->date('birth_date')->nullable();
            $table->date('employment_date')->nullable();
            $table->string('status')->default('active'); // 'active', 'fired'
            $table->string('specialty_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birth_date', 'employment_date', 'status', 'specialty_id']);
        });
    }
};
