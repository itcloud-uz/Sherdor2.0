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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone_number');
            $table->enum('type', ['inbound', 'outbound', 'auto_dialer']);
            $table->enum('status', ['answered', 'missed', 'failed']);
            $table->integer('duration_seconds')->default(0);
            $table->string('recording_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
