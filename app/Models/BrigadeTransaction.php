<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrigadeTransaction extends Model
{
    protected $fillable = [
        'brigade_id',
        'type',
        'amount',
        'description',
        'date',
        'linked_work_type',
        'linked_work_id'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function brigade()
    {
        return $this->belongsTo(Brigade::class);
    }
}
