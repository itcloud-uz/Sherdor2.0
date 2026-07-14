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
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (!Schema::hasColumn('leads', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (!Schema::hasColumn('leads', 'middle_name')) {
                $table->string('middle_name')->nullable();
            }
            if (!Schema::hasColumn('leads', 'pinfl')) {
                $table->string('pinfl')->nullable();
            }
            if (!Schema::hasColumn('leads', 'passport_photo')) {
                $table->string('passport_photo')->nullable();
            }
            if (!Schema::hasColumn('leads', 'passport_photo_back')) {
                $table->string('passport_photo_back')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'middle_name',
                'pinfl',
                'passport_photo',
                'passport_photo_back'
            ]);
        });
    }
};
