<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonyCampaign extends Model
{
    protected $fillable = ['target_group', 'audio_id', 'schedule_time', 'schedule_days', 'is_active'];

    public function leads()
    {
        return $this->hasMany(TelephonyLead::class, 'campaign_id');
    }

    public function audio()
    {
        return $this->belongsTo(TelephonyAudio::class, 'audio_id');
    }
}
