<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $fillable = [
        'name',
        'total_floors',
        'address',
        'estimated_budget',
        'actual_expenses',
        'status',
        'city',
        'district',
        'mahalla',
        'street',
        'blocks_count',
        'entrances_count',
        'total_apartments',
        'total_shops',
        'shops_per_floor',
        'apartments_per_floor',
        'blueprint_path',
        'photo_path',
        'block_name',
        'corpus_name',
        'length_m',
        'width_m',
        'height_m',
        'model_3d_path',
        'basement_floors',
    ];

    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    public function stages()
    {
        return $this->hasMany(ConstructionStage::class);
    }
}
