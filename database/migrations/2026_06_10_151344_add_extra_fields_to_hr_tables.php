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
            $table->string('contract_type')->nullable(); // 'labor', 'civil'
            $table->text('characteristics')->nullable(); // JSON list of traits
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('contract_type')->nullable(); // 'labor', 'civil'
            $table->text('characteristics')->nullable(); // JSON list of traits
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'characteristics']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'characteristics']);
        });
    }
};
