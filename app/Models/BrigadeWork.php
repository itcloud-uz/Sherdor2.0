<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrigadeWork extends Model
{
    protected $fillable = [
        'brigade_id', 'floor_info', 'room_info', 'service_name', 'unit',
        'measure1', 'measure2', 'total_measure', 'quantity', 'total',
        'price', 'calculated_sum', 'date', 'note'
    ];

    public function brigade()
    {
        return $this->belongsTo(Brigade::class);
    }
}
