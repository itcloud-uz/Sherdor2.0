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
        Schema::create('telephony_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('target_group');
            $table->unsignedBigInteger('audio_id');
            $table->time('schedule_time');
            $table->string('schedule_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('audio_id')->references('id')->on('telephony_audios')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telephony_campaigns');
    }
};
