<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_address',
        'company_bank_account',
        'company_bank_name',
        'company_mfo',
        'company_inn',
        'company_director',
        'company_phone',
        'logo_path',
        'primary_theme_color',
        'neon_glow_intensity',
        'default_currency',
        'tax_rate',
        'default_penalty_rate',
        'asterisk_sip_host',
        'asterisk_sip_username',
        'asterisk_sip_password',
        'asterisk_port',
        'timezone',
        'currency_symbol',
        'promo_title',
        'promo_subtitle',
        'promo_description',
        'promo_image',
        'promo_form_title',
        'promo_form_subtitle',
        'promo_button_text',
        'promo_success_message',
        'telegram_lead_bot_token',
        'telegram_lead_chat_id',
        'background_image_path',
        'ai_is_active',
        'ai_prompt',
        'ai_audio_path',
        'ai_min_score',
        'ai_max_duration',
        'openai_api_key',
        'vapi_api_key',
        'vapi_assistant_id',
        'ai_auto_call',
        'fb_pixel_id'
    ];
}
