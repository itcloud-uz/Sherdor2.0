<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareApiKey extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime'
    ];
}
