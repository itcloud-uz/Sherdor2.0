<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $fillable = ['building_id', 'podez_id', 'floor_number', 'blueprint_path', 'facade_polygon', 'total_units', 'total_commercial_units', 'total_parking_units'];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function podez()
    {
        return $this->belongsTo(Podez::class, 'podez_id');
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
