<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'email')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('email')->nullable()->after('phone');
            });
        }
        if (!Schema::hasColumn('clients', 'is_blacklisted')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->boolean('is_blacklisted')->default(false)->after('passport_issued_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'email')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
        if (Schema::hasColumn('clients', 'is_blacklisted')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('is_blacklisted');
            });
        }
    }
};
