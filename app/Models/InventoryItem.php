<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name', 'sku', 'unit', 'quantity', 'min_quantity', 'price', 'category', 'status'
    ];

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
