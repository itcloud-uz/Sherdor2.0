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
        'total_parking',
        'shops_per_floor',
        'apartments_per_floor',
        'blueprint_path',
        'photo_path',
        'block_name',
        'corpus_name',
        'construction_area',
        'length_m',
        'width_m',
        'height_m',
        'model_3d_path',
        'basement_floors',
        'showroom_images',
        'facade_image',
        'facade_data',
        'master_plan_image',
        'master_plan_polygon',
        'master_plan_data',
    ];

    protected $casts = [
        'showroom_images' => 'array',
        'facade_data' => 'array',
        'master_plan_data' => 'array',
    ];

    public function floors()
    {
        return $this->hasMany(Floor::class);
    }

    public function units()
    {
        return $this->hasManyThrough(Unit::class, Floor::class);
    }

    public function podezes()
    {
        return $this->hasMany(Podez::class, 'building_id');
    }

    public function stages()
    {
        return $this->hasMany(ConstructionStage::class);
    }

    public function smetaItems()
    {
        return $this->hasMany(SmetaItem::class);
    }
}
