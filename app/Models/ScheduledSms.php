<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledSms extends Model
{
    protected $table = 'scheduled_sms';

    protected $fillable = [
        'phone_number',
        'message',
        'scheduled_at',
        'client_id',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
