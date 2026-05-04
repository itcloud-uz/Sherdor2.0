<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('pdf_path')->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->string('ai_verification_status')->default('pending');
            $table->json('ai_verification_logs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_path',
                'signed_pdf_path',
                'ai_verification_status',
                'ai_verification_logs'
            ]);
        });
    }
};
