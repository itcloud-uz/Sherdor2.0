<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'user_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'user_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
