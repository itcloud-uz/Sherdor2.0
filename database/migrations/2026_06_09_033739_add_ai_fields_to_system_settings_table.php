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
            $table->boolean('ai_is_active')->default(false);
            $table->text('ai_prompt')->nullable();
            $table->string('ai_audio_path')->nullable();
            $table->integer('ai_min_score')->default(50);
            $table->integer('ai_max_duration')->default(60);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_is_active', 'ai_prompt', 'ai_audio_path', 'ai_min_score', 'ai_max_duration']);
        });
    }
};
