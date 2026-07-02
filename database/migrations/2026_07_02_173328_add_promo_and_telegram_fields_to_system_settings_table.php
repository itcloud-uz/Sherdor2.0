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
            $table->string('promo_form_title')->nullable()->after('promo_description');
            $table->string('promo_form_subtitle')->nullable()->after('promo_form_title');
            $table->string('promo_button_text')->nullable()->after('promo_form_subtitle');
            $table->text('promo_success_message')->nullable()->after('promo_button_text');
            
            $table->string('telegram_lead_bot_token')->nullable()->after('promo_success_message');
            $table->string('telegram_lead_chat_id')->nullable()->after('telegram_lead_bot_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'promo_form_title',
                'promo_form_subtitle',
                'promo_button_text',
                'promo_success_message',
                'telegram_lead_bot_token',
                'telegram_lead_chat_id'
            ]);
        });
    }
};
