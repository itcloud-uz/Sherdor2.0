<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstructionStage extends Model
{
    protected $fillable = [
        'building_id',
        'name',
        'progress_percentage',
        'target_volume',
        'current_volume',
        'start_date',
        'end_date',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }
}
