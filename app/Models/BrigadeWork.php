<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrigadeWork extends Model
{
    protected $fillable = [
        'brigade_id', 'floor_info', 'room_info', 'service_name', 'unit',
        'measure1', 'measure2', 'total_measure', 'quantity', 'total',
        'price', 'calculated_sum', 'date', 'note',
        'work_name', 'building', 'block', 'floor', 'work_date'
    ];

    public function brigade()
    {
        return $this->belongsTo(Brigade::class);
    }

    public function transactions()
    {
        return $this->hasMany(BrigadeTransaction::class, 'linked_work_id')->where('linked_work_type', self::class);
    }
}
