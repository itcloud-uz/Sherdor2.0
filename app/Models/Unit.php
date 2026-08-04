<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'floor_id',
        'podez_id',
        'unit_number',
        'type',
        'area_sqm',
        'price',
        'status',
        'svg_polygon',
        'rooms_count',
        'kitchens_count',
        'bathrooms_count',
        'toilets_count',
        'orientation',
        'podyezd',
        'has_balcony',
        'blueprint_path',
        'model_3d_path',
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function podez()
    {
        return $this->belongsTo(Podez::class, 'podez_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'unit_id');
    }

    public function activeContract()
    {
        return $this->hasOne(Contract::class, 'unit_id')->latestOfMany();
    }
}
