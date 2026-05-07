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
            $table->string('asterisk_sip_host')->nullable();
            $table->string('asterisk_sip_username')->nullable();
            $table->text('asterisk_sip_password')->nullable();
            $table->integer('asterisk_port')->default(5060);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'asterisk_sip_host',
                'asterisk_sip_username',
                'asterisk_sip_password',
                'asterisk_port'
            ]);
        });
    }
};
