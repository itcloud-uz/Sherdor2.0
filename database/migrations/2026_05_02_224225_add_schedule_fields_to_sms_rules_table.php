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
            $table->string('schedule_days')->nullable()->after('schedule_time'); // e.g. "1,3,5" for Mon,Wed,Fri
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_automation_rules', function (Blueprint $table) {
            $table->dropColumn('schedule_days');
        });
    }
};
