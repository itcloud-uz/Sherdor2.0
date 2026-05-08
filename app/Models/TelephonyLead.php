<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonyLead extends Model
{
    protected $fillable = ['campaign_id', 'phone_number', 'status'];

    public function campaign()
    {
        return $this->belongsTo(TelephonyCampaign::class, 'campaign_id');
    }
}
