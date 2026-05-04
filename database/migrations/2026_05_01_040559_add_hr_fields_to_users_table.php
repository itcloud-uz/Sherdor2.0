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
            $table->string('pinfl')->nullable();
            $table->string('passport')->nullable();
            $table->decimal('salary_rate', 10, 2)->nullable();
            $table->string('face_encoding_id')->nullable()->unique();
            $table->timestamp('last_entry')->nullable();
            $table->timestamp('last_exit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pinfl', 'passport', 'salary_rate', 'face_encoding_id', 'last_entry', 'last_exit']);
        });
    }
};
