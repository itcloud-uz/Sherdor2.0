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
            if (!Schema::hasColumn('leads', 'passport_issued_date')) {
                $table->date('passport_issued_date')->nullable();
            }
            if (!Schema::hasColumn('leads', 'passport_issued_by')) {
                $table->string('passport_issued_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['passport_issued_date', 'passport_issued_by']);
        });
    }
};
