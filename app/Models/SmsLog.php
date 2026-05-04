<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = ['phone_number', 'message', 'eskiz_uuid', 'status', 'campaign_type', 'client_id'];
}
