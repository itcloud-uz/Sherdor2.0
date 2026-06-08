<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
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
        'promo_title',
        'promo_subtitle',
        'promo_description',
        'promo_image',
        'background_image_path',
        'ai_is_active',
        'ai_prompt',
        'ai_audio_path',
        'ai_min_score',
        'ai_max_duration',
        'openai_api_key',
        'vapi_api_key',
        'vapi_assistant_id',
        'ai_auto_call'
    ];
}
