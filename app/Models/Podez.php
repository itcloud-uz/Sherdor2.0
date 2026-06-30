<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Podez extends Model
{
    protected $table = 'podezes';

    protected $fillable = [
        'building_id',
        'podez_name',
        'podez_number',
        'podez_type',
        'total_floors',
        'status',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function floors()
    {
        return $this->hasMany(Floor::class, 'podez_id');
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'podez_id');
    }
}
