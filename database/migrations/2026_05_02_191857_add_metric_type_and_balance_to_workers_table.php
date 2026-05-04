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
            $table->string('metric_type')->nullable()->after('salary_type'); // e.g. dona, kv_m, tochka, soat, reys, oylik
            $table->decimal('balance', 15, 2)->default(0)->after('base_rate'); // Track kirim/chiqim
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn(['metric_type', 'balance']);
        });
    }
};
