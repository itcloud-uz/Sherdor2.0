<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonyAudio extends Model
{
    protected $table = 'telephony_audios';
    protected $fillable = ['name', 'file_path'];
}
