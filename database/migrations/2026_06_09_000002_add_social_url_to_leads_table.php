<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'social_url')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('social_url')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'social_url')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('social_url');
            });
        }
    }
};
