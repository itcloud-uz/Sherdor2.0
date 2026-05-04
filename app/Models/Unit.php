<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'floor_id',
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
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }
}
