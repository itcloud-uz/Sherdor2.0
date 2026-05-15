<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'name',
        'type',
        'purchase_date',
        'purchase_cost',
        'service_life_months',
        'status',
        'description',
        'partner_id'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function getServiceDurationMonthsAttribute()
    {
        return now()->diffInMonths($this->purchase_date);
    }
}
