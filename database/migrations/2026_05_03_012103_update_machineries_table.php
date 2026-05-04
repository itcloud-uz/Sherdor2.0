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
        Schema::table('machineries', function (Blueprint $table) {
            $table->string('owner_name')->nullable();
            $table->string('machinery_type')->nullable(); // e.g. Ekskavator, Kamaz
            $table->string('payment_type')->default('hourly'); // hourly, volume
            $table->decimal('balance', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machineries', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'machinery_type', 'payment_type', 'balance']);
        });
    }
};
