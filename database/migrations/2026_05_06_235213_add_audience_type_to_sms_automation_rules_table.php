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
        Schema::table('sms_automation_rules', function (Blueprint $table) {
            $table->string('audience_type')->default('all')->after('schedule_days'); // 'all', 'system_clients', 'uploaded_file', 'manual_phone'
            $table->string('manual_phone')->nullable()->after('audience_type');
            $table->string('uploaded_file_path')->nullable()->after('manual_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_automation_rules', function (Blueprint $table) {
            $table->dropColumn(['audience_type', 'manual_phone', 'uploaded_file_path']);
        });
    }
};
