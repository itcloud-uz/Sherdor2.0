<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadConversionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'client_id',
        'user_id',
        'lead_name',
        'lead_phone',
        'action',
        'description',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
