<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('buildings', 'construction_area')) {
            Schema::table('buildings', function (Blueprint $table) {
                $table->decimal('construction_area', 14, 2)->nullable()->after('corpus_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('buildings', 'construction_area')) {
            Schema::table('buildings', function (Blueprint $table) {
                $table->dropColumn('construction_area');
            });
        }
    }
};
