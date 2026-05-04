<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_stages', function (Blueprint $table) {
            $table->decimal('target_volume', 15, 2)->default(0);
            $table->decimal('current_volume', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('construction_stages', function (Blueprint $table) {
            $table->dropColumn(['target_volume', 'current_volume']);
        });
    }
};
