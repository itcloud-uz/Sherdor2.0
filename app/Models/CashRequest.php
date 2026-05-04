<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRequest extends Model
{
    protected $fillable = ['user_id', 'building_id', 'type', 'amount', 'target_type', 'target_id', 'description', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }
}
