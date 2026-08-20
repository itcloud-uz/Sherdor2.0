<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmetaItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'building_id',
        'block_name',
        'category_name',
        'item_name',
        'unit_type',
        'planned_quantity',
        'planned_price',
        'planned_total',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'planned_quantity' => 'float',
        'planned_price' => 'float',
        'planned_total' => 'float',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
