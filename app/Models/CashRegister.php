<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = [
        'name',
        'type',
        'currency',
        'balance',
        'building_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }
}
