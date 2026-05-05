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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('account_type')->default('main')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this is tricky if we added custom string values.
        // It's safe to just leave it as string in down or drop if we were sure.
        Schema::table('transactions', function (Blueprint $table) {
            // $table->enum('account_type', ['main', 'credit'])->default('main')->change();
        });
    }
};
