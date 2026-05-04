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
        'default_penalty_rate'
    ];
}
