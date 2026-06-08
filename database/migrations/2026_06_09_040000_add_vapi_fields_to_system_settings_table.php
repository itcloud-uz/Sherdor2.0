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
        Schema::table('system_settings', function (Blueprint $table) {
            $table->text('openai_api_key')->nullable();
            $table->text('vapi_api_key')->nullable();
            $table->string('vapi_assistant_id')->nullable();
            $table->boolean('ai_auto_call')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key', 'vapi_api_key', 'vapi_assistant_id', 'ai_auto_call']);
        });
    }
};
